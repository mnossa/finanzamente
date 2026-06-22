<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\User;
use App\Services\SystemVariableResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PacFormulaVariablesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pac_formula_variables_resolve_for_user(): void
    {
        Carbon::setTestNow('2026-06-15');

        $user = User::factory()->create(['plan' => 'pro']);
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);

        InvestmentPac::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'investment_asset_id' => $asset->id,
            'amount' => 150,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-01-10',
            'status' => 'active',
        ]);

        $resolver = app(SystemVariableResolver::class);

        $this->assertSame(150.0, $resolver->resolve($user, 'pac_monthly_total', Carbon::today(), Carbon::today()));
        $this->assertSame(1.0, $resolver->resolve($user, 'pac_active_count', Carbon::today(), Carbon::today()));
        $this->assertGreaterThan(0.0, $resolver->resolveForSeries($user, 'pac_projected_contributions', Carbon::today()->addMonths(2)));

        Carbon::setTestNow();
    }

    #[Test]
    public function transactions_index_includes_upcoming_movements(): void
    {
        Carbon::setTestNow('2026-06-04');

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);

        InvestmentPac::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'investment_asset_id' => $asset->id,
            'amount' => 100,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-06-05',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('upcomingMovements', 1)
                ->where('upcomingMovements.0.is_virtual', true)
            );

        Carbon::setTestNow();
    }
}
