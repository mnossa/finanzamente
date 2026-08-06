<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\MealVoucherLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountBalanceService $service;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AccountBalanceService::class);
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function compute_balance_is_initial_plus_transaction_sum(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'currency_code' => 'EUR',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => -150,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => 50,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        $this->assertSame(900.0, $this->service->computeBalance($account, $this->user));
    }

    #[Test]
    public function compute_balance_includes_transactions_without_category(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 5000,
            'currency_code' => 'EUR',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => -250,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        $this->assertSame(4750.0, $this->service->computeBalance($account, $this->user));
    }

    #[Test]
    public function sync_stored_balance_persists_computed_value(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 2000,
            'current_balance' => 9999,
            'currency_code' => 'EUR',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => -100,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        $this->service->syncStoredBalance($account, $this->user);

        $this->assertSame(1900.0, (float) $account->fresh()->current_balance);
    }

    #[Test]
    public function compute_household_total_sums_active_accounts(): void
    {
        Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 3000,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 2000,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'active' => false,
            'currency_code' => 'EUR',
        ]);

        $this->assertSame(5000.0, $this->service->computeHouseholdTotal($this->user));
    }

    #[Test]
    public function compute_household_total_excludes_locked_accounts_by_default(): void
    {
        Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 3000,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        Account::factory()->savingsDeposit()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 4000,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        Account::factory()->pensionFund()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 10000,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        $this->assertSame(3000.0, $this->service->computeHouseholdTotal($this->user));
        $this->assertSame(17000.0, $this->service->computeHouseholdTotal($this->user, includeLocked: true));
    }

    #[Test]
    public function map_accounts_with_balance_exposes_meal_voucher_labels_and_ticket_count(): void
    {
        $meal = Account::factory()->mealVoucher(8)->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 80,
            'current_balance' => 80,
            'active' => true,
            'currency_code' => 'EUR',
        ]);
        app(MealVoucherLedgerService::class)->initializeAccount($meal);

        $bank = Account::factory()->bank()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 100,
            'active' => true,
            'currency_code' => 'EUR',
        ]);

        $mapped = $this->service->mapAccountsWithBalance(
            collect([$meal->fresh(), $bank->fresh()]),
            $this->user,
        )->keyBy('id');

        $this->assertSame('Buoni pasto', $mapped[$meal->id]['type_label']);
        $this->assertTrue($mapped[$meal->id]['is_meal_voucher']);
        $this->assertSame(10, $mapped[$meal->id]['ticket_count']);
        $this->assertSame('Conto Bancario', $mapped[$bank->id]['type_label']);
        $this->assertFalse($mapped[$bank->id]['is_meal_voucher']);
        $this->assertNull($mapped[$bank->id]['ticket_count']);
    }

    #[Test]
    public function apply_delta_updates_stored_balance(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'currency_code' => 'EUR',
        ]);

        $this->service->applyDelta($account, -150.5);

        $this->assertSame(849.5, (float) $account->fresh()->current_balance);
    }

    #[Test]
    public function apply_delta_ignores_zero(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'currency_code' => 'EUR',
        ]);

        $updatedAt = $account->fresh()->updated_at;
        $this->service->applyDelta($account, 0.0);

        $this->assertSame(1000.0, (float) $account->fresh()->current_balance);
        $this->assertTrue($account->fresh()->updated_at->eq($updatedAt));
    }

    #[Test]
    public function affects_stored_balance_excludes_future_dates(): void
    {
        Carbon::setTestNow('2026-06-15');

        $this->assertTrue($this->service->affectsStoredBalance('2026-06-15'));
        $this->assertTrue($this->service->affectsStoredBalance('2026-06-01'));
        $this->assertFalse($this->service->affectsStoredBalance('2026-06-16'));
    }
}
