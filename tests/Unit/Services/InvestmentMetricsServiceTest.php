<?php

namespace Tests\Unit\Services;

use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Services\InvestmentMetricsService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InvestmentMetricsServiceTest extends TestCase
{
    #[Test]
    public function fetch_current_prices_for_investments_returns_empty_without_market_apis(): void
    {
        $assetA = new InvestmentAsset(['symbol' => 'AAA']);
        $assetB = new InvestmentAsset(['symbol' => 'BBB']);
        $openA = new Investment(['sell_date' => null, 'quantity' => 1, 'buy_price' => 1]);
        $openA->setRelation('asset', $assetA);
        $openB = new Investment(['sell_date' => null, 'quantity' => 1, 'buy_price' => 1]);
        $openB->setRelation('asset', $assetB);

        $service = new InvestmentMetricsService;
        $prices = $service->fetchCurrentPricesForInvestments(new Collection([$openA, $openB]));

        $this->assertSame([], $prices);
    }

    #[Test]
    public function unrealized_profit_subtracts_purchase_fees(): void
    {
        $asset = new InvestmentAsset(['symbol' => 'SWDA', 'currency_code' => 'EUR']);
        $investment = new Investment([
            'quantity' => 10,
            'buy_price' => 100,
            'fees' => 5,
            'sell_date' => null,
        ]);
        $investment->setRelation('asset', $asset);

        $service = new InvestmentMetricsService;

        $metrics = $service->unrealizedMetrics($investment, 110.0);

        $this->assertSame(110.0, $metrics['current_price']);
        $this->assertSame(1100.0, $metrics['current_value']);
        $this->assertSame(95.0, $metrics['unrealized_profit']);
    }

    #[Test]
    public function total_cost_includes_fees(): void
    {
        $investment = new Investment([
            'quantity' => 2,
            'buy_price' => 50,
            'fees' => 3,
        ]);

        $service = new InvestmentMetricsService;

        $this->assertSame(103.0, $service->totalCost($investment));
    }
}
