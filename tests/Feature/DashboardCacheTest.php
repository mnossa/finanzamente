<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'currency_code' => 'EUR',
        ]);
    }

    #[Test]
    public function dashboard_reflects_new_transaction_after_cache_invalidation(): void
    {
        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('totalBalance', 1000)
            );

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'amount' => -200,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        $this->account->refresh();

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('totalBalance', 800)
            );
    }

    #[Test]
    public function dashboard_cache_service_stores_payload_until_data_version_changes(): void
    {
        $cacheService = app(DashboardCacheService::class);
        $calls = 0;

        $first = $cacheService->rememberIndexPayload($this->user, function () use (&$calls) {
            $calls++;

            return ['totalBalance' => 1000];
        });

        $second = $cacheService->rememberIndexPayload($this->user, function () use (&$calls) {
            $calls++;

            return ['totalBalance' => 9999];
        });

        $this->assertSame(['totalBalance' => 1000], $first);
        $this->assertSame(['totalBalance' => 1000], $second);
        $this->assertSame(1, $calls);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => -50,
            'date' => now()->toDateString(),
            'currency_code' => 'EUR',
        ]);

        $third = $cacheService->rememberIndexPayload($this->user, function () use (&$calls) {
            $calls++;

            return ['totalBalance' => 950];
        });

        $this->assertSame(['totalBalance' => 950], $third);
        $this->assertSame(2, $calls);
    }
}
