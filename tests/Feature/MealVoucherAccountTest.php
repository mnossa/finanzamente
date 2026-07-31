<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\MealVoucherLot;
use App\Models\MealVoucherUnitValue;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MealVoucherLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MealVoucherAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function cannot_create_meal_voucher_account_without_ticket_unit_value(): void
    {
        $this->actingAs($this->user)
            ->post(route('accounts.store'), [
                'name' => 'Buoni Edenred',
                'type' => 'meal_voucher',
                'initial_balance' => 80,
                'currency_code' => 'EUR',
                'is_private' => false,
            ])
            ->assertSessionHasErrors('ticket_unit_value');
    }

    #[Test]
    public function can_create_meal_voucher_account_with_opening_lot(): void
    {
        $this->actingAs($this->user)
            ->post(route('accounts.store'), [
                'name' => 'Buoni Edenred',
                'type' => 'meal_voucher',
                'initial_balance' => 80,
                'ticket_unit_value' => 8,
                'currency_code' => 'EUR',
                'is_private' => false,
            ])
            ->assertRedirect(route('accounts.index'));

        $account = Account::query()->where('name', 'Buoni Edenred')->firstOrFail();
        $this->assertSame('meal_voucher', $account->type);
        $this->assertDatabaseHas('meal_voucher_unit_values', [
            'account_id' => $account->id,
            'unit_value' => 8,
        ]);
        $this->assertDatabaseHas('meal_voucher_lots', [
            'account_id' => $account->id,
            'unit_value' => 8,
            'quantity_remaining' => 10,
        ]);
    }

    #[Test]
    public function show_exposes_lots_and_ticket_count(): void
    {
        $account = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 80,
            'current_balance' => 80,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($account);

        $this->actingAs($this->user)
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounts/Show')
                ->where('account.type', 'meal_voucher')
                ->where('account.ticket_unit_value', 8)
                ->where('account.ticket_count', 10)
                ->has('mealVoucherLots', 1)
            );
    }

    #[Test]
    public function can_schedule_new_unit_value_without_recalculating_lots(): void
    {
        $account = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 80,
            'current_balance' => 80,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($account);

        $this->actingAs($this->user)
            ->post(route('accounts.meal-voucher-unit-value.store', $account), [
                'unit_value' => 10,
                'effective_from' => now()->addDay()->toDateString(),
            ])
            ->assertRedirect(route('accounts.show', $account));

        $this->assertDatabaseHas('meal_voucher_unit_values', [
            'account_id' => $account->id,
            'unit_value' => 10,
        ]);
        $this->assertSame(10, (int) MealVoucherLot::query()->where('account_id', $account->id)->sum('quantity_remaining'));
        $this->assertEquals(8.0, (float) MealVoucherLot::query()->where('account_id', $account->id)->value('unit_value'));
        $this->assertEquals(8.0, (float) $account->fresh()->ticket_unit_value);
    }

    #[Test]
    public function can_set_unit_value_in_the_past_for_historical_categorization(): void
    {
        $account = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($account);

        $pastDate = now()->subMonths(2)->toDateString();

        $this->actingAs($this->user)
            ->post(route('accounts.meal-voucher-unit-value.store', $account), [
                'unit_value' => 7,
                'effective_from' => $pastDate,
            ])
            ->assertRedirect(route('accounts.show', $account))
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            MealVoucherUnitValue::query()
                ->where('account_id', $account->id)
                ->where('unit_value', 7)
                ->whereDate('effective_from', $pastDate)
                ->exists()
        );

        $ledger = app(MealVoucherLedgerService::class);
        $this->assertEquals(7.0, $ledger->unitValueOn($account->fresh(), Carbon::parse($pastDate)));
        // Valore corrente (oggi) resta quello più recente vigente: 8 dall'inizializzazione
        $this->assertEquals(8.0, (float) $account->fresh()->ticket_unit_value);
    }

    #[Test]
    public function past_income_uses_historical_unit_value(): void
    {
        $account = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);
        $ledger = app(MealVoucherLedgerService::class);
        $ledger->initializeAccount($account);

        $pastDate = now()->subMonth()->startOfDay();
        $ledger->scheduleUnitValue($account, 5, $pastDate);

        $incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
                'amount' => 25,
                'date' => $pastDate->toDateString(),
                'description' => 'Ricarica storica',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('meal_voucher_lots', [
            'account_id' => $account->id,
            'unit_value' => 5,
            'quantity_remaining' => 5,
        ]);
    }

    #[Test]
    public function income_creates_lot_at_current_unit_value(): void
    {
        $account = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($account);

        app(MealVoucherLedgerService::class)->scheduleUnitValue(
            $account,
            10,
            now()->addDay(),
        );

        Carbon::setTestNow(now()->addDay());

        $incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'account_id' => $account->id,
                'category_id' => $incomeCategory->id,
                'amount' => 50,
                'date' => now()->toDateString(),
                'description' => 'Ricarica buoni',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('meal_voucher_lots', [
            'account_id' => $account->id,
            'unit_value' => 10,
            'quantity_remaining' => 5,
        ]);

        Carbon::setTestNow();
    }

    #[Test]
    public function expense_requires_whole_tickets_from_chosen_lots(): void
    {
        $account = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 80,
            'current_balance' => 80,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($account);
        $lot = MealVoucherLot::query()->where('account_id', $account->id)->firstOrFail();

        $expenseCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'account_id' => $account->id,
                'category_id' => $expenseCategory->id,
                'amount' => 16,
                'date' => now()->toDateString(),
                'description' => 'Pranzo',
                'meal_voucher_lines' => [
                    ['lot_id' => $lot->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(8, (int) $lot->fresh()->quantity_remaining);
        $this->assertSame(8, app(MealVoucherLedgerService::class)->totalTicketCount($account->fresh()));
    }

    #[Test]
    public function create_form_prefers_liquid_account_over_meal_voucher_as_default(): void
    {
        $meal = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'AAA Buoni pasto',
            'initial_balance' => 80,
            'current_balance' => 80,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($meal);

        $bank = Account::factory()->bank()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'ZZZ Conto corrente',
            'initial_balance' => 100,
            'current_balance' => 100,
        ]);

        $this->actingAs($this->user)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Transactions/Create')
                ->where('defaultAccountId', (string) $bank->id)
            );
    }

    #[Test]
    public function expense_rejects_fractional_euro_without_matching_tickets(): void
    {
        $account = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 80,
            'current_balance' => 80,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($account);

        $expenseCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'account_id' => $account->id,
                'category_id' => $expenseCategory->id,
                'amount' => 12.5,
                'date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('meal_voucher_lines');
    }

    #[Test]
    public function fifo_suggestion_prefers_oldest_lots(): void
    {
        $account = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($account);

        MealVoucherLot::query()->create([
            'account_id' => $account->id,
            'unit_value' => 8,
            'quantity_remaining' => 3,
            'acquired_on' => Carbon::parse('2026-01-01'),
        ]);
        MealVoucherLot::query()->create([
            'account_id' => $account->id,
            'unit_value' => 10,
            'quantity_remaining' => 5,
            'acquired_on' => Carbon::parse('2026-06-01'),
        ]);

        $lines = app(MealVoucherLedgerService::class)->suggestFifoForEuro($account, 34);
        $this->assertCount(2, $lines);
        $this->assertSame(3, $lines[0]['quantity']);
        $this->assertSame(8.0, $lines[0]['unit_value']);
        $this->assertSame(1, $lines[1]['quantity']);
        $this->assertSame(10.0, $lines[1]['unit_value']);
    }

    #[Test]
    public function moving_income_transaction_to_meal_voucher_updates_ticket_count(): void
    {
        $checking = Account::factory()->bank()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 100,
            'current_balance' => 140,
        ]);
        $meal = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 80,
            'current_balance' => 80,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($meal);

        $incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
        ]);

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $checking->id,
            'category_id' => $incomeCategory->id,
            'amount' => 40,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
            'description' => 'Ricarica da spostare',
        ]);

        $this->assertSame(10, app(MealVoucherLedgerService::class)->totalTicketCount($meal->fresh()));

        $this->actingAs($this->user)
            ->patch(route('transactions.update', $tx), [
                'account_id' => $meal->id,
                'category_id' => $incomeCategory->id,
                'amount' => 40,
                'date' => now()->toDateString(),
                'description' => 'Ricarica da spostare',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(15, app(MealVoucherLedgerService::class)->totalTicketCount($meal->fresh()));
        $this->assertSame($meal->id, $tx->fresh()->account_id);
    }

    #[Test]
    public function moving_expense_transaction_to_meal_voucher_updates_ticket_count_via_fifo(): void
    {
        $checking = Account::factory()->bank()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 100,
            'current_balance' => 84,
        ]);
        $meal = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 80,
            'current_balance' => 80,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($meal);

        $expenseCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $checking->id,
            'category_id' => $expenseCategory->id,
            'amount' => -16,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
            'description' => 'Pranzo da spostare',
        ]);

        $this->actingAs($this->user)
            ->patch(route('transactions.update', $tx), [
                'account_id' => $meal->id,
                'category_id' => $expenseCategory->id,
                'amount' => 16,
                'date' => now()->toDateString(),
                'description' => 'Pranzo da spostare',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(8, app(MealVoucherLedgerService::class)->totalTicketCount($meal->fresh()));
    }

    #[Test]
    public function moving_transaction_off_meal_voucher_restores_tickets(): void
    {
        $checking = Account::factory()->bank()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);
        $meal = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 80,
            'current_balance' => 80,
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($meal);
        $lot = MealVoucherLot::query()->where('account_id', $meal->id)->firstOrFail();

        $expenseCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'account_id' => $meal->id,
                'category_id' => $expenseCategory->id,
                'amount' => 16,
                'date' => now()->toDateString(),
                'description' => 'Pranzo',
                'meal_voucher_lines' => [
                    ['lot_id' => $lot->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $tx = Transaction::query()->where('description', 'Pranzo')->firstOrFail();
        $this->assertSame(8, app(MealVoucherLedgerService::class)->totalTicketCount($meal->fresh()));

        $this->actingAs($this->user)
            ->patch(route('transactions.update', $tx), [
                'account_id' => $checking->id,
                'category_id' => $expenseCategory->id,
                'amount' => 16,
                'date' => now()->toDateString(),
                'description' => 'Pranzo',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(10, app(MealVoucherLedgerService::class)->totalTicketCount($meal->fresh()));
        $this->assertSame($checking->id, $tx->fresh()->account_id);
    }
}
