<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    /**
     * @return array<int, array{month: string, Patrimonio Netto: float}>
     */
    public function getNetWorthSeries(int $householdId, int $userId, ?Carbon $startDate = null): array
    {
        $endDate = Carbon::now()->endOfDay();
        $startDate = $startDate ?? $this->resolveHistoryStartDate($householdId, $userId);

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('owner_user_id', $userId))
            ->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        $initialBalance = $accounts->sum(fn ($a) => (float) $a->initial_balance);

        $balanceBeforePeriod = (float) Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->where('date', '<', $startDate)
            ->sum('amount');

        $runningBalance = $initialBalance + $balanceBeforePeriod;

        $isSqlite = DB::getDriverName() === 'sqlite';
        $yearExpr = $isSqlite ? "CAST(strftime('%Y', date) AS INTEGER)" : 'YEAR(date)';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', date) AS INTEGER)" : 'MONTH(date)';

        $monthlyTransactions = Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw("{$yearExpr} as year, {$monthExpr} as month, SUM(amount) as net")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}")
            ->get()
            ->keyBy(fn ($r) => "{$r->year}-{$r->month}");

        $result = [];
        $current = $startDate->copy()->startOfMonth();

        while ($current->lte($endDate)) {
            $key = $current->year.'-'.$current->month;
            if (isset($monthlyTransactions[$key])) {
                $runningBalance += (float) $monthlyTransactions[$key]->net;
            }
            $result[] = [
                'month' => $current->translatedFormat('M Y'),
                'Patrimonio Netto' => round($runningBalance, 2),
            ];
            $current->addMonth();
        }

        return $result;
    }

    /**
     * @return array<int, array{month: string, Entrate: float, Uscite: float, Risparmio: float}>
     */
    public function getCashFlowSeries(int $householdId, int $userId, ?Carbon $startDate = null): array
    {
        $endDate = Carbon::now()->endOfDay();
        $startDate = $startDate ?? $this->resolveHistoryStartDate($householdId, $userId);

        $isSqlite = DB::getDriverName() === 'sqlite';
        $yearExpr = $isSqlite ? "CAST(strftime('%Y', date) AS INTEGER)" : 'YEAR(date)';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', date) AS INTEGER)" : 'MONTH(date)';

        $transactions = Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereBetween('date', [$startDate, $endDate])
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
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(ABS(amount)) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $grandTotal = $expenses->sum('total');

        return $expenses->map(function ($row) use ($grandTotal) {
            $category = $row->category;

            return [
                'name' => $category?->name ?? 'Senza categoria',
                'value' => round((float) $row->total, 2),
                'percentage' => $grandTotal > 0 ? round(((float) $row->total / (float) $grandTotal) * 100, 1) : 0,
                'color' => $category?->color,
                'icon' => $category?->icon,
                'category_id' => $category?->id,
            ];
        })->values()->toArray();
    }

    public function resolveHistoryStartDate(int $householdId, int $userId): Carbon
    {
        $firstTransaction = Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->min('date');

        if ($firstTransaction) {
            return Carbon::parse($firstTransaction)->startOfMonth();
        }

        $firstAccount = Account::where('household_id', $householdId)
            ->where('active', true)
            ->min('created_at');

        if ($firstAccount) {
            return Carbon::parse($firstAccount)->startOfMonth();
        }

        return Carbon::now()->subYear()->startOfMonth();
    }

    /**
     * @return array{start: string, end: string, growth_pct: float|null}
     */
    public function summarizeNetWorth(array $series): array
    {
        if ($series === []) {
            return ['start' => '', 'end' => '', 'growth_pct' => null];
        }

        $first = $series[0]['Patrimonio Netto'];
        $last = $series[count($series) - 1]['Patrimonio Netto'];
        $growth = $first != 0.0 ? round((($last - $first) / abs($first)) * 100, 1) : null;

        return [
            'start' => $series[0]['month'],
            'end' => $series[count($series) - 1]['month'],
            'growth_pct' => $growth,
        ];
    }
}
