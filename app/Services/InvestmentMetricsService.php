<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class InvestmentMetricsService
{
    public function totalCost(Investment $investment): float
    {
        return (float) $investment->total_buy_value + (float) ($investment->fees ?? 0);
    }

    public function totalCoupons(Investment $investment): float
    {
        return (float) Transaction::query()
            ->where('investment_id', $investment->id)
            ->where('investment_event', 'coupon')
            ->sum('amount');
    }

    /**
     * Ritorno complessivo = P/L capitale (realizzato o non) + cedole.
     *
     * @return array{
     *     coupons_total: float,
     *     capital_profit: float|null,
     *     total_return: float|null
     * }
     */
    public function totalReturnMetrics(Investment $investment, ?float $unrealizedProfit): array
    {
        $coupons = $this->totalCoupons($investment);
        $capital = $investment->isSold()
            ? ($investment->net_profit !== null ? (float) $investment->net_profit : null)
            : $unrealizedProfit;

        return [
            'coupons_total' => round($coupons, 2),
            'capital_profit' => $capital !== null ? round($capital, 2) : null,
            'total_return' => $capital !== null ? round($capital + $coupons, 2) : ($coupons > 0 ? round($coupons, 2) : null),
        ];
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
     * Market price APIs removed: returns empty map (manual prices only).
     *
     * @param  Collection<int, Investment>  $investments
     * @return array<string, float>
     */
    public function fetchCurrentPricesForInvestments(Collection $investments): array
    {
        unset($investments);

        return [];
    }

    /**
     * Without market APIs, treat the paid amount as a single lot at that price.
     *
     * @return array{quantity: float, buy_price: float, nav_at_buy: float|null}
     */
    public function resolvePurchaseLot(float $amount, ?string $symbol, string $buyDate): array
    {
        unset($symbol, $buyDate);

        return [
            'quantity' => 1,
            'buy_price' => $amount,
            'nav_at_buy' => null,
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
