<?php

namespace App\Services;

use App\Models\Investment;
use Illuminate\Support\Collection;

class InvestmentMetricsService
{
    public function __construct(private readonly AssetPriceService $assetPriceService) {}

    public function totalCost(Investment $investment): float
    {
        return (float) $investment->total_buy_value + (float) ($investment->fees ?? 0);
    }

    /**
     * @return array{current_price: float|null, current_value: float|null, unrealized_profit: float|null}
     */
    public function unrealizedMetrics(Investment $investment, ?float $currentPrice): array
    {
        if ($investment->isSold() || $currentPrice === null) {
            return [
                'current_price' => $currentPrice,
                'current_value' => null,
                'unrealized_profit' => null,
            ];
        }

        $quantity = (float) $investment->quantity;
        $currentValue = $currentPrice * $quantity;
        $totalCost = $this->totalCost($investment);

        return [
            'current_price' => $currentPrice,
            'current_value' => round($currentValue, 2),
            'unrealized_profit' => round($currentValue - $totalCost, 2),
        ];
    }

    /**
     * @param  Collection<int, Investment>  $investments
     * @return array<string, float>
     */
    public function fetchCurrentPricesForInvestments(Collection $investments): array
    {
        $symbols = $investments
            ->filter(fn (Investment $inv) => $inv->isOpen() && $inv->relationLoaded('asset') && $inv->asset?->symbol)
            ->pluck('asset.symbol')
            ->unique()
            ->values()
            ->all();

        return $this->assetPriceService->getCurrentPrices($symbols);
    }

    /**
     * @return array{quantity: float, buy_price: float, nav_at_buy: float|null}
     */
    public function resolvePurchaseLot(float $amount, ?string $symbol, string $buyDate): array
    {
        if ($symbol === null || $symbol === '') {
            return [
                'quantity' => 1,
                'buy_price' => $amount,
                'nav_at_buy' => null,
            ];
        }

        $historical = $this->assetPriceService->getHistoricalPrice($symbol, $buyDate);
        $nav = (! $historical['error'] && isset($historical['price']))
            ? (float) $historical['price']
            : null;

        if ($nav === null) {
            $current = $this->assetPriceService->getCurrentPrice($symbol);
            if (! $current['error'] && isset($current['price'])) {
                $nav = (float) $current['price'];
            }
        }

        if ($nav === null || $nav <= 0) {
            return [
                'quantity' => 1,
                'buy_price' => $amount,
                'nav_at_buy' => null,
            ];
        }

        return [
            'quantity' => round($amount / $nav, 8),
            'buy_price' => $nav,
            'nav_at_buy' => $nav,
        ];
    }

    /**
     * @param  Collection<int, Investment>  $openInvestments
     */
    public function sumUnrealizedProfit(Collection $openInvestments, array $currentPrices): float
    {
        return (float) $openInvestments->sum(function (Investment $investment) use ($currentPrices) {
            $symbol = $investment->asset?->symbol;
            $price = ($symbol && isset($currentPrices[$symbol])) ? $currentPrices[$symbol] : null;
            $metrics = $this->unrealizedMetrics($investment, $price);

            return $metrics['unrealized_profit'] ?? 0;
        });
    }
}
