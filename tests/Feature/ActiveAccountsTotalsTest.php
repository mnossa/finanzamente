<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActiveAccountsTotalsTest extends TestCase
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
    public function accounts_page_total_balance_uses_only_active_accounts(): void
    {
        Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'active' => true,
            'is_private' => false,
            'current_balance' => 1000,
            'initial_balance' => 1000,
        ]);

        Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'active' => false,
            'is_private' => false,
            'current_balance' => 5000,
            'initial_balance' => 5000,
        ]);

        $response = $this->actingAs($this->user)->get(route('accounts.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounts/Index')
            ->where('totalBalance', 1000)
        );
    }

    #[Test]
    public function dashboard_monthly_stats_ignore_archived_accounts_transactions(): void
    {
        $activeAccount = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'active' => true,
            'is_private' => false,
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);

        $archivedAccount = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'active' => false,
            'is_private' => false,
            'initial_balance' => 0,
            'current_balance' => 0,
        ]);

        Transaction::factory()->create([
            'account_id' => $activeAccount->id,
            'user_id' => $this->user->id,
            'amount' => 300,
            'date' => now()->toDateString(),
            'is_private' => false,
        ]);

        Transaction::factory()->create([
            'account_id' => $activeAccount->id,
            'user_id' => $this->user->id,
            'amount' => -100,
            'date' => now()->toDateString(),
            'is_private' => false,
        ]);

        Transaction::factory()->create([
            'account_id' => $archivedAccount->id,
            'user_id' => $this->user->id,
            'amount' => 1000,
            'date' => now()->toDateString(),
            'is_private' => false,
        ]);

        Transaction::factory()->create([
            'account_id' => $archivedAccount->id,
            'user_id' => $this->user->id,
            'amount' => -700,
            'date' => now()->toDateString(),
            'is_private' => false,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('monthlyStats.income', 300)
            ->where('monthlyStats.expenses', 100)
            ->where('monthlyStats.net', 200)
            ->where('monthlyStats.transaction_count', 2)
        );
    }
}
