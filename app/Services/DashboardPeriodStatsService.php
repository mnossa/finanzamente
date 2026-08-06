<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class DashboardPeriodStatsService
{
    /**
     * @return array{income: float, expenses: float, net: float, transaction_count: int}
     */
    public function calculate(User $user, Carbon $startDate, Carbon $endDate, ?int $accountId = null): array
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return [
                'income' => 0.0,
                'expenses' => 0.0,
                'net' => 0.0,
                'transaction_count' => 0,
            ];
        }

        $effectiveEnd = $endDate->copy();
        if ($effectiveEnd->gt(Carbon::today())) {
            $effectiveEnd = Carbon::today();
        }

        $query = Transaction::whereHas('account', function ($query) use ($householdId, $user, $accountId) {
            $query->where('household_id', $householdId)
                ->where('active', true);

            if ($accountId !== null) {
                $query->where('id', $accountId)
                    ->where(function ($q) use ($user) {
                        $q->where('is_private', false)
                            ->orWhere('owner_user_id', $user->id);
                    });
            }
        })
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->whereBetween('date', [$startDate, $effectiveEnd])
            ->operationalStats();

        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        }

        $income = (float) (clone $query)->where('amount', '>', 0)->sum('amount');
        $expenses = (float) abs((clone $query)->where('amount', '<', 0)->sum('amount'));
        $net = $income - $expenses;
        $transactionCount = (clone $query)->count();

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'net' => round($net, 2),
            'transaction_count' => $transactionCount,
        ];
    }
}
