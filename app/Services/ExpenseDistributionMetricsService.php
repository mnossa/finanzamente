<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class ExpenseDistributionMetricsService
{
    public function __construct(
        private readonly InvestmentLedgerService $investmentLedgerService,
    ) {}

    /**
     * @return array{needs: float, wants: float, investments: float, total: float}
     */
    public function calculate(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return [
                'needs' => 0.0,
                'wants' => 0.0,
                'investments' => 0.0,
                'total' => 0.0,
            ];
        }

        $expenses = Transaction::with('category')
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->whereBetween('date', [$startDate, $endDate])
            ->where('amount', '<', 0)
            ->whereNull('transfer_id')
            ->excludeInterHouseholdStats()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(ABS(amount)) as total')
            ->groupBy('category_id')
            ->get();

        $buckets = [
            'needs' => 0.0,
            'wants' => 0.0,
            'investments' => 0.0,
        ];

        foreach ($expenses as $row) {
            $dist = $row->category?->expense_distribution ?? null;
            if (! in_array($dist, ['needs', 'wants', 'investments'], true)) {
                continue;
            }

            $buckets[$dist] += (float) $row->total;
        }

        $unsynced = $this->investmentLedgerService->unsyncedPurchasesInPeriod($user, $startDate, $endDate);
        $buckets['investments'] += $unsynced['amount'];

        $total = array_sum($buckets);

        return [
            'needs' => round($buckets['needs'], 2),
            'wants' => round($buckets['wants'], 2),
            'investments' => round($buckets['investments'], 2),
            'total' => round($total, 2),
        ];
    }
}
