<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\DatabaseDialect;
use Carbon\Carbon;

class DashboardAnalyticsService
{
    public function __construct(private readonly NetWorthSeriesService $netWorthSeriesService) {}

    /**
     * @return array<int, array{month: string, Patrimonio: float}>
     */
    public function getNetWorthSeries(int $householdId, int $userId, ?Carbon $startDate = null): array
    {
        $start = $startDate ?? $this->netWorthSeriesService->resolveHistoryStartDate($householdId, $userId);

        return $this->netWorthSeriesService->buildForChart($householdId, $userId, 'portfolio', $start);
    }

    /**
     * @return array<int, array{month: string, Entrate: float, Uscite: float, Risparmio: float}>
     */
    public function getCashFlowSeries(int $householdId, int $userId, ?Carbon $startDate = null): array
    {
        $endDate = Carbon::now()->endOfDay();
        $startDate = $startDate ?? $this->netWorthSeriesService->resolveHistoryStartDate($householdId, $userId);

        $yearExpr = DatabaseDialect::yearExpr('date');
        $monthExpr = DatabaseDialect::monthExpr('date');

        $transactions = Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNull('transfer_id')
            ->selectRaw("{$yearExpr} as year, {$monthExpr} as month, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income, SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}")
            ->get();

        $byKey = $transactions->keyBy(fn ($row) => "{$row->year}-{$row->month}");

        $result = [];
        $current = $startDate->copy()->startOfMonth();

        while ($current->lte($endDate)) {
            $key = $current->year.'-'.$current->month;
            $row = $byKey->get($key);
            $income = $row ? (float) $row->income : 0.0;
            $expenses = $row ? (float) $row->expenses : 0.0;
            $result[] = [
                'month' => $current->translatedFormat('M Y'),
                'Entrate' => round($income, 2),
                'Uscite' => round($expenses, 2),
                'Risparmio' => round($income - $expenses, 2),
            ];
            $current->addMonth();
        }

        return $result;
    }

    /**
     * @return array<int, array{name: string, value: float, percentage: float, color: string|null, icon: string|null, category_id: int|null}>
     */
    public function getExpenseCategorySeries(int $householdId, int $userId, ?Carbon $month = null): array
    {
        $month = $month ?? Carbon::now();
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();

        $expenses = Transaction::with('category')
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereBetween('date', [$startDate, $endDate])
            ->where('amount', '<', 0)
            ->whereNull('transfer_id')
            ->selectRaw('category_id, SUM(ABS(amount)) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $grandTotal = (float) $expenses->sum('total');

        return $expenses->map(function ($row) use ($grandTotal) {
            $value = (float) $row->total;

            return [
                'name' => $row->category?->name ?? 'Senza categoria',
                'value' => round($value, 2),
                'percentage' => $grandTotal > 0 ? round(($value / $grandTotal) * 100, 1) : 0,
                'color' => $row->category?->color,
                'icon' => $row->category?->icon,
                'category_id' => $row->category_id,
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, array{month: string, Patrimonio: float}>  $series
     * @return array{start: float, end: float, growth_pct: float|null}
     */
    public function summarizeNetWorth(array $series): array
    {
        if ($series === []) {
            return ['start' => 0.0, 'end' => 0.0, 'growth_pct' => null];
        }

        $start = (float) $series[0]['Patrimonio'];
        $end = (float) $series[array_key_last($series)]['Patrimonio'];
        $growth = ($start != 0.0) ? round((($end - $start) / abs($start)) * 100, 1) : null;

        return [
            'start' => round($start, 2),
            'end' => round($end, 2),
            'growth_pct' => $growth,
        ];
    }

    public function resolveHistoryStartDate(int $householdId, int $userId): Carbon
    {
        return $this->netWorthSeriesService->resolveHistoryStartDate($householdId, $userId);
    }
}
