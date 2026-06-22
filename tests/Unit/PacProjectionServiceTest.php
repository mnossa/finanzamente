<?php

namespace Tests\Unit;

use App\Models\Household;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\User;
use App\Services\PacProjectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PacProjectionServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function projection_aggregates_active_pacs_over_horizon(): void
    {
        Carbon::setTestNow('2026-06-15');

        $user = User::factory()->create();
        $household = Household::factory()->create();
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);

        InvestmentPac::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'investment_asset_id' => $asset->id,
            'amount' => 100,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'status' => 'active',
            'start_date' => '2026-01-10',
        ]);

        InvestmentPac::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'investment_asset_id' => $asset->id,
            'amount' => 50,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'status' => 'paused',
            'start_date' => '2026-01-10',
        ]);

        $projection = app(PacProjectionService::class)->buildHouseholdProjection($user, 3);

        $this->assertSame(100.0, $projection['monthly_total']);
        $this->assertSame(1, $projection['active_pac_count']);
        $this->assertNotEmpty($projection['series']);
        $this->assertGreaterThan(0.0, collect($projection['series'])->sum('contributions'));
    }
}
