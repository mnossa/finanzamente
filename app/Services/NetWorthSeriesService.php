<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\DatabaseDialect;
use Carbon\Carbon;

class NetWorthSeriesService
{
    public function __construct(
        private readonly InvestmentLedgerService $investmentLedgerService,
    ) {}

    /**
     * @return array<int, array{month: string, value: float, label: string}>
     */
    public function build(
        int $householdId,
        int $userId,
        string $mode = 'portfolio',
        ?Carbon $startDate = null,
    ): array {
        $user = User::findOrFail($userId);
        $endDate = Carbon::now()->endOfDay();
        $startDate = $startDate ?? Carbon::now()->subYear()->startOfMonth();

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('owner_user_id', $userId))
            ->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        $cashAccounts = $mode === 'cash'
            ? $accounts->reject(fn (Account $account) => $account->isLockedBalance())->values()
            : $accounts;

        if ($cashAccounts->isEmpty() && $mode === 'cash') {
            return [];
        }

        $initialBalance = $cashAccounts->sum(fn ($a) => (float) $a->initial_balance);

        $cashAccountIds = $cashAccounts->pluck('id');

        $balanceBeforePeriod = (float) Transaction::whereIn('account_id', $cashAccountIds)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->where('date', '<', $startDate)
            ->sum('amount');

        $runningCash = $initialBalance + $balanceBeforePeriod;

        $yearExpr = DatabaseDialect::yearExpr('date');
        $monthExpr = DatabaseDialect::monthExpr('date');

        $monthlyTransactions = Transaction::whereIn('account_id', $cashAccountIds)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw("{$yearExpr} as year, {$monthExpr} as month, SUM(amount) as net")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}")
            ->get()
            ->keyBy(fn ($r) => "{$r->year}-{$r->month}");

        $label = $mode === 'cash' ? 'Liquidità' : 'Patrimonio';
        $result = [];
        $current = $startDate->copy()->startOfMonth();

        while ($current->lte($endDate)) {
            $key = $current->year.'-'.$current->month;
            if (isset($monthlyTransactions[$key])) {
                $runningCash += (float) $monthlyTransactions[$key]->net;
            }

            $monthEnd = $current->copy()->endOfMonth();
            $value = $mode === 'cash'
                ? $runningCash
                : $runningCash + $this->investmentLedgerService->linkedInvestedValueAt($user, $monthEnd);

            $result[] = [
                'month' => $current->translatedFormat('M Y'),
                'value' => round($value, 2),
                'label' => $label,
            ];
            $current->addMonth();
        }

        return $result;
    }

    /**
     * @return array<int, array{month: string, Patrimonio: float}>
     */
    public function buildForChart(int $householdId, int $userId, string $mode = 'portfolio', ?Carbon $startDate = null): array
    {
        return array_map(
            fn (array $row) => [
                'month' => $row['month'],
                'Patrimonio' => $row['value'],
            ],
            $this->build($householdId, $userId, $mode, $startDate),
        );
    }

    public function resolveHistoryStartDate(int $householdId, int $userId): Carbon
    {
        $firstTx = Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->orderBy('date')
            ->value('date');

        if ($firstTx) {
            return Carbon::parse($firstTx)->startOfMonth();
        }

        $firstAccount = Account::where('household_id', $householdId)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('owner_user_id', $userId))
            ->orderBy('created_at')
            ->value('created_at');

        if ($firstAccount) {
            return Carbon::parse($firstAccount)->startOfMonth();
        }

        return Carbon::now()->subYear()->startOfMonth();
    }
}
