<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
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

        $this->assertDatabaseMissing('accounts', [
            'household_id' => $this->household->id,
            'name' => 'Buoni Edenred',
        ]);
    }

    #[Test]
    public function can_create_meal_voucher_account_with_ticket_unit_value(): void
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

        $this->assertDatabaseHas('accounts', [
            'household_id' => $this->household->id,
            'name' => 'Buoni Edenred',
            'type' => 'meal_voucher',
            'ticket_unit_value' => 8,
            'interest_rate' => null,
        ]);
    }

    #[Test]
    public function show_exposes_ticket_count_from_balance_and_tickets_delta_on_transactions(): void
    {
        $account = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 80,
            'current_balance' => 80,
        ]);

        $expenseCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $this->user->id,
            'category_id' => $expenseCategory->id,
            'amount' => -16,
            'date' => now()->toDateString(),
            'description' => 'Pranzo',
        ]);

        $account->recalculateBalance();

        $this->actingAs($this->user)
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounts/Show')
                ->where('account.type', 'meal_voucher')
                ->where('account.ticket_unit_value', 8)
                ->where('account.current_balance', 64)
                ->where('account.ticket_count', 8)
                ->where('recentTransactions.0.tickets_delta', -2)
            );
    }

    #[Test]
    public function show_omits_ticket_fields_for_non_meal_voucher_accounts(): void
    {
        $account = Account::factory()->bank()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 100,
            'current_balance' => 100,
        ]);

        $this->actingAs($this->user)
            ->get(route('accounts.show', $account))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounts/Show')
                ->where('account.ticket_unit_value', null)
                ->where('account.ticket_count', null)
            );
    }

    #[Test]
    public function can_store_expense_on_meal_voucher_account(): void
    {
        $account = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 80,
            'current_balance' => 80,
        ]);

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
                'description' => 'Pranzo con buoni',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount' => -16,
        ]);

        $account->refresh();
        $this->assertSame(8, $account->ticketCountFromBalance((float) $account->current_balance));
    }
}
