<?php

namespace Tests\Unit;

use App\Services\PortfolioSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortfolioSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function group_investment_positions_aggregates_pac_movements(): void
    {
        $service = app(PortfolioSnapshotService::class);

        $positions = [
            [
                'id' => 10,
                'name' => 'iShares Core S&P 500',
                'symbol' => 'CSSPX.MI',
                'value' => 60.0,
                'portfolio_percentage' => 0.1,
                'buy_date' => '2024-01-08',
                'account' => ['id' => 1, 'name' => 'Matteo Personale'],
                'currency' => ['code' => 'EUR', 'symbol' => '€'],
                'asset_class' => 'equities',
                'asset_class_label' => 'Azionario',
                'investment_pac_id' => 5,
                'investment_pac' => ['id' => 5, 'status' => 'active'],
            ],
            [
                'id' => 11,
                'name' => 'iShares Core S&P 500',
                'symbol' => 'CSSPX.MI',
                'value' => 60.0,
                'portfolio_percentage' => 0.1,
                'buy_date' => '2024-02-08',
                'account' => ['id' => 1, 'name' => 'Matteo Personale'],
                'currency' => ['code' => 'EUR', 'symbol' => '€'],
                'asset_class' => 'equities',
                'asset_class_label' => 'Azionario',
                'investment_pac_id' => 5,
                'investment_pac' => ['id' => 5, 'status' => 'active'],
            ],
            [
                'id' => 20,
                'name' => 'Vanguard FTSE All-World',
                'symbol' => 'VWCE',
                'value' => 500.0,
                'portfolio_percentage' => 5.0,
                'buy_date' => '2024-03-01',
                'account' => ['id' => 2, 'name' => 'Lara Personale'],
                'currency' => ['code' => 'EUR', 'symbol' => '€'],
                'asset_class' => 'equities',
                'asset_class_label' => 'Azionario',
                'investment_pac_id' => null,
                'investment_pac' => null,
            ],
        ];

        $groups = $service->groupInvestmentPositionsForDisplay($positions);

        $this->assertCount(2, $groups);
        $this->assertSame('standalone', $groups[0]['kind']);
        $this->assertSame(20, $groups[0]['id']);
        $this->assertSame('pac', $groups[1]['kind']);
        $this->assertSame(5, $groups[1]['pac_id']);
        $this->assertSame(2, $groups[1]['movement_count']);
        $this->assertSame(120.0, $groups[1]['value']);
        $this->assertSame(0.2, $groups[1]['portfolio_percentage']);
        $this->assertCount(2, $groups[1]['movements']);
    }
}
