<?php

namespace Tests\Unit\Services;

use App\Models\Investment;
use App\Services\AssetPriceService;
use App\Services\InvestmentMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unrealized_profit_is_current_value_minus_cost_basis(): void
    {
        $service = app(InvestmentMetricsService::class);

        $investment = new Investment([
            'quantity' => 10,
            'buy_price' => 50,
        ]);

        $metrics = $service->unrealizedMetrics($investment, 55.0);

        $this->assertSame(55.0, $metrics['current_price']);
        $this->assertSame(550.0, $metrics['current_value']);
        $this->assertSame(50.0, $metrics['unrealized_profit']);
    }

    #[Test]
    public function unrealized_profit_is_null_for_sold_investment(): void
    {
        $service = app(InvestmentMetricsService::class);

        $investment = new Investment([
            'quantity' => 5,
            'buy_price' => 100,
            'sell_date' => '2026-01-01',
        ]);

        $metrics = $service->unrealizedMetrics($investment, 120.0);

        $this->assertNull($metrics['unrealized_profit']);
    }

    #[Test]
    public function sum_unrealized_profit_aggregates_distinct_open_lots(): void
    {
        $service = app(InvestmentMetricsService::class);

        $lotA = new Investment(['quantity' => 2, 'buy_price' => 100]);
        $lotA->setRelation('asset', (object) ['symbol' => 'SWDA']);

        $lotB = new Investment(['quantity' => 3, 'buy_price' => 100]);
        $lotB->setRelation('asset', (object) ['symbol' => 'SWDA']);

        $lotC = new Investment(['quantity' => 1, 'buy_price' => 100]);
        $lotC->setRelation('asset', (object) ['symbol' => 'SWDA']);

        $total = $service->sumUnrealizedProfit(
            Collection::make([$lotA, $lotB, $lotC]),
            ['SWDA' => 110.0],
        );

        // (2+3+1)*110 - (200+300+100) = 660 - 600 = 60
        $this->assertSame(60.0, $total);
    }

    #[Test]
    public function resolve_purchase_lot_uses_historical_nav_when_available(): void
    {
        $priceService = $this->createMock(AssetPriceService::class);
        $priceService->method('getHistoricalPrice')->willReturn([
            'error' => false,
            'price' => 50.0,
        ]);

        $service = new InvestmentMetricsService($priceService);

        $lot = $service->resolvePurchaseLot(200.0, 'SWDA', '2026-01-15');

        $this->assertSame(4.0, $lot['quantity']);
        $this->assertSame(50.0, $lot['buy_price']);
        $this->assertSame(50.0, $lot['nav_at_buy']);
    }

    #[Test]
    public function resolve_purchase_lot_falls_back_when_price_unavailable(): void
    {
        $priceService = $this->createMock(AssetPriceService::class);
        $priceService->method('getHistoricalPrice')->willReturn(['error' => true]);
        $priceService->method('getCurrentPrice')->willReturn(['error' => true]);

        $service = new InvestmentMetricsService($priceService);

        $lot = $service->resolvePurchaseLot(150.0, 'SWDA', '2026-01-15');

        $this->assertEquals(1.0, $lot['quantity']);
        $this->assertEquals(150.0, $lot['buy_price']);
        $this->assertNull($lot['nav_at_buy']);
    }
}
