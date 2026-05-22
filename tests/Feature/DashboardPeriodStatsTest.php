<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardPeriodStatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dashboard_stats_use_last_30_days_not_calendar_month(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => 500,
            'date' => now()->subDays(10)->toDateString(),
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -200,
            'date' => now()->subDays(5)->toDateString(),
        ]);

        // Fuori finestra 30 giorni: non deve entrare nelle statistiche correnti
        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => 9000,
            'date' => now()->subDays(40)->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('periodLabel', 'Ultimi 30 giorni')
            ->where('previousPeriodLabel', '30 giorni precedenti')
            ->where('monthlyStats.income', 500)
            ->where('monthlyStats.expenses', 200)
            ->where('monthlyStats.transaction_count', 2)
        );
    }
}
