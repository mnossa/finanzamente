<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FutureTransactionBalanceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function future_transaction_does_not_reduce_current_balance(): void
    {
        Carbon::setTestNow('2026-06-15');

        $user = User::factory()->create(['user_type' => 'persona']);
        $household = Household::factory()->create();
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -100,
            'date' => '2026-06-10',
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -50,
            'date' => '2026-07-01',
        ]);

        $balanceService = app(AccountBalanceService::class);

        $this->assertSame(900.0, $balanceService->computeBalance($account->fresh(), $user));
        $this->assertSame(900.0, (float) $account->fresh()->current_balance);
    }

    #[Test]
    public function past_transaction_reduces_current_balance(): void
    {
        Carbon::setTestNow('2026-06-15');

        $user = User::factory()->create(['user_type' => 'persona']);
        $household = Household::factory()->create();
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -200,
            'date' => '2026-06-01',
        ]);

        $balanceService = app(AccountBalanceService::class);

        $this->assertSame(800.0, $balanceService->computeBalance($account->fresh(), $user));
        $this->assertSame(800.0, (float) $account->fresh()->current_balance);
    }
}
