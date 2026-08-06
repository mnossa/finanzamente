<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteTransactionsForAccountCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_requires_force_or_dry_run(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'current_balance' => 100,
        ]);

        $this->artisan('transactions:delete-for-account', ['account_id' => (string) $account->id])
            ->assertFailed();
    }

    #[Test]
    public function dry_run_does_not_delete_transactions(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'initial_balance' => 50,
            'current_balance' => 50,
        ]);

        Transaction::factory()->count(2)->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
        ]);

        $this->artisan('transactions:delete-for-account', [
            'account_id' => (string) $account->id,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(2, Transaction::query()->where('account_id', $account->id)->count());
    }

    #[Test]
    public function force_deletes_all_transactions_for_account_and_adjusts_balance(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'initial_balance' => 50,
            'current_balance' => 50,
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => 100.00,
        ]);
        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -40.00,
        ]);

        $this->artisan('transactions:delete-for-account', [
            'account_id' => (string) $account->id,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(0, Transaction::query()->where('account_id', $account->id)->count());
        $account->refresh();
        $this->assertEqualsWithDelta(50.0, (float) $account->current_balance, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $account->initial_balance, 0.001);
    }

    #[Test]
    public function command_is_blocked_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        $this->artisan('transactions:delete-for-account', [
            'account_id' => (string) $account->id,
            '--dry-run' => true,
        ])->assertFailed();
    }
}
