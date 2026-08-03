<?php

namespace Tests\Unit\Listeners;

use App\Listeners\UpdateAccountBalance;
use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateAccountBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15');

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function create_past_transaction_applies_delta(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => -100,
            'date' => '2026-06-10',
            'currency_code' => 'EUR',
        ]);

        $this->assertSame(900.0, (float) $account->fresh()->current_balance);
    }

    #[Test]
    public function create_future_transaction_does_not_change_stored_balance(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => -100,
            'date' => '2026-07-01',
            'currency_code' => 'EUR',
        ]);

        $this->assertSame(1000.0, (float) $account->fresh()->current_balance);
    }

    #[Test]
    public function delete_past_transaction_reverts_delta(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => -100,
            'date' => '2026-06-10',
            'currency_code' => 'EUR',
        ]);

        $tx->delete();

        $this->assertSame(1000.0, (float) $account->fresh()->current_balance);
    }

    #[Test]
    public function update_amount_applies_difference(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => -100,
            'date' => '2026-06-10',
            'currency_code' => 'EUR',
        ]);

        $tx->update(['amount' => -250]);

        $this->assertSame(750.0, (float) $account->fresh()->current_balance);
    }

    #[Test]
    public function update_date_from_future_to_past_applies_amount(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => -100,
            'date' => '2026-07-01',
            'currency_code' => 'EUR',
        ]);

        $this->assertSame(1000.0, (float) $account->fresh()->current_balance);

        $tx->update(['date' => '2026-06-01']);

        $this->assertSame(900.0, (float) $account->fresh()->current_balance);
    }

    #[Test]
    public function update_date_from_past_to_future_removes_amount(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => -100,
            'date' => '2026-06-01',
            'currency_code' => 'EUR',
        ]);

        $tx->update(['date' => '2026-07-01']);

        $this->assertSame(1000.0, (float) $account->fresh()->current_balance);
    }

    #[Test]
    public function move_account_transfers_delta(): void
    {
        $from = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);
        $to = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 500,
            'current_balance' => 500,
        ]);

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $from->id,
            'amount' => -100,
            'date' => '2026-06-10',
            'currency_code' => 'EUR',
        ]);

        $tx->update(['account_id' => $to->id]);

        $this->assertSame(1000.0, (float) $from->fresh()->current_balance);
        $this->assertSame(400.0, (float) $to->fresh()->current_balance);
    }

    #[Test]
    public function without_balance_sync_skips_listener(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        UpdateAccountBalance::withoutBalanceSync(function () use ($account) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $account->id,
                'amount' => -100,
                'date' => '2026-06-10',
                'currency_code' => 'EUR',
            ]);
        });

        $this->assertSame(1000.0, (float) $account->fresh()->current_balance);
    }
}
