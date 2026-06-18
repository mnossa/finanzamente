<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\DashboardDataVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardDataVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardDataVersionService $versionService;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionService = app(DashboardDataVersionService::class);
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function version_changes_when_transaction_is_created(): void
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'currency_code' => 'EUR',
        ]);

        $before = $this->versionService->resolveForUser($this->user->fresh());

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => -25,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        $after = $this->versionService->resolveForUser($this->user->fresh());

        $this->assertNotSame($before, $after);
    }

    #[Test]
    public function batch_compute_balances_matches_individual_compute_balance(): void
    {
        $service = app(AccountBalanceService::class);

        $first = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'currency_code' => 'EUR',
        ]);

        $second = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 500,
            'currency_code' => 'EUR',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $first->id,
            'amount' => -100,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $second->id,
            'amount' => 50,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        $accounts = collect([$first, $second]);
        $batch = $service->batchComputeBalances($accounts, $this->user);

        $this->assertSame(900.0, $batch[$first->id]);
        $this->assertSame(550.0, $batch[$second->id]);
        $this->assertSame(900.0, $service->computeBalance($first, $this->user));
        $this->assertSame(550.0, $service->computeBalance($second, $this->user));
    }
}
