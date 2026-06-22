<?php

namespace App\Services;

use App\Models\Account;
use App\Models\InvestmentPac;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class UpcomingCashflowService
{
    public const PAC_VIRTUAL_ID_OFFSET = 1_000_000_000;

    public function __construct(
        private readonly RecurringTransactionService $recurringTransactionService,
        private readonly InvestmentPacService $investmentPacService,
        private readonly AccountBalanceService $accountBalanceService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buildVirtualMovements(User $user, int $horizonDays = 90, ?int $accountId = null): array
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return [];
        }

        $today = Carbon::today();
        $horizonEnd = $today->copy()->addDays($horizonDays);
        $movements = [];

        $recurringQuery = RecurringTransaction::query()
            ->with(['account:id,name,currency_code', 'category:id,name,color,icon,type'])
            ->whereHas('account', function ($query) use ($householdId, $user) {
                $query->where('household_id', $householdId)
                    ->where(function ($q) use ($user) {
                        $q->where('is_private', false)
                            ->orWhere('owner_user_id', $user->id);
                    });
            });

        if ($accountId !== null) {
            $recurringQuery->where('account_id', $accountId);
        }

        foreach ($recurringQuery->get() as $recurring) {
            if (! $this->recurringTransactionService->isActive($recurring)) {
                continue;
            }

            $nextDue = $this->recurringTransactionService->calculateNextDueDate($recurring);

            if ($nextDue === null || $nextDue->lte($today) || $nextDue->gt($horizonEnd)) {
                continue;
            }

            $movements[] = $this->mapRecurringVirtualRow($recurring, $nextDue);
        }

        $pacQuery = InvestmentPac::query()
            ->with(['asset:id,name,symbol', 'account:id,name,currency_code'])
            ->where('household_id', $householdId)
            ->where('status', 'active');

        if ($accountId !== null) {
            $pacQuery->where('account_id', $accountId);
        }

        foreach ($pacQuery->get() as $pac) {
            $nextExecution = $this->investmentPacService->calculateNextExecutionDate($pac);

            if ($nextExecution === null || $nextExecution->lte($today) || $nextExecution->gt($horizonEnd)) {
                continue;
            }

            $movements[] = $this->mapPacVirtualRow($pac, $nextExecution);
        }

        usort($movements, fn (array $a, array $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);

        return $movements;
    }

    public function projectedHouseholdBalance(User $user, int $horizonDays = 90): float
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return 0.0;
        }

        $current = $this->accountBalanceService->computeHouseholdTotal($user);
        $today = Carbon::today();
        $horizonEnd = $today->copy()->addDays($horizonDays);

        $futureReal = (float) Transaction::query()
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->whereDate('date', '>', $today)
            ->whereDate('date', '<=', $horizonEnd)
            ->sum('amount');

        $virtualTotal = 0.0;

        foreach ($this->buildVirtualMovements($user, $horizonDays) as $movement) {
            $virtualTotal += (float) $movement['amount'];
        }

        return round($current + $futureReal + $virtualTotal, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildUpcomingMovements(User $user, ?int $accountId = null, int $horizonDays = 90): array
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return [];
        }

        $today = Carbon::today();
        $horizonEnd = $today->copy()->addDays($horizonDays);
        $movements = $this->buildVirtualMovements($user, $horizonDays, $accountId);

        $realFutureQuery = Transaction::query()
            ->with([
                'account:id,name,currency_code',
                'category:id,name,color,icon,type',
                'recurringTransaction:id,description,frequency',
                'investment.investmentPac.asset:id,name',
            ])
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->whereDate('date', '>', $today)
            ->whereDate('date', '<=', $horizonEnd)
            ->orderBy('date')
            ->orderBy('created_at');

        if ($accountId !== null) {
            $realFutureQuery->where('account_id', $accountId);
        }

        foreach ($realFutureQuery->get() as $transaction) {
            $movements[] = [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'date' => $transaction->date->format('Y-m-d'),
                'description' => $transaction->description,
                'is_private' => $transaction->is_private,
                'is_tax_deductible' => $transaction->is_tax_deductible,
                'transfer_id' => $transaction->transfer_id,
                'refund_id' => $transaction->refund_id,
                'has_refunds' => false,
                'is_fully_refunded' => false,
                'attachments_count' => 0,
                'category' => $transaction->category ? [
                    'id' => $transaction->category->id,
                    'name' => $transaction->category->name,
                    'color' => $transaction->category->color,
                    'icon' => $transaction->category->icon,
                    'type' => $transaction->category->type,
                ] : null,
                'account' => [
                    'id' => $transaction->account->id,
                    'name' => $transaction->account->name,
                    'currency_code' => $transaction->account->currency_code,
                ],
                'tags' => [],
                'recurring_transaction_id' => $transaction->recurring_transaction_id,
                'recurring_summary' => $transaction->recurringTransaction ? [
                    'id' => $transaction->recurringTransaction->id,
                    'description' => $transaction->recurringTransaction->description,
                    'frequency' => $transaction->recurringTransaction->frequency,
                ] : null,
                'investment_id' => $transaction->investment_id,
                'is_investment' => $transaction->investment_id !== null,
                'is_pac' => $transaction->isPacLedger(),
                'pac_summary' => $transaction->isPacLedger() ? [
                    'id' => $transaction->investment?->investment_pac_id,
                    'asset_name' => $transaction->investment?->investmentPac?->asset?->name,
                ] : null,
                'is_future' => true,
                'is_virtual' => false,
                'virtual_source' => null,
                'virtual_source_id' => null,
                'projected_balance_after' => null,
            ];
        }

        usort($movements, fn (array $a, array $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);

        $accounts = Account::query()
            ->where('household_id', $householdId)
            ->where('active', true)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('owner_user_id', $user->id))
            ->get();

        $settledBalances = $this->accountBalanceService->batchComputeBalances($accounts, $user);
        $runningByAccount = $settledBalances;

        foreach ($movements as $index => $movement) {
            $accountIdForRow = (int) ($movement['account']['id'] ?? 0);

            if ($accountIdForRow <= 0) {
                continue;
            }

            $runningByAccount[$accountIdForRow] = ($runningByAccount[$accountIdForRow] ?? 0.0) + (float) $movement['amount'];
            $movements[$index]['projected_balance_after'] = round((float) $runningByAccount[$accountIdForRow], 2);
        }

        return $movements;
    }

    public static function virtualRecurringId(int $recurringId): int
    {
        return -$recurringId;
    }

    public static function virtualPacId(int $pacId): int
    {
        return -(self::PAC_VIRTUAL_ID_OFFSET + $pacId);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRecurringVirtualRow(RecurringTransaction $recurring, Carbon $dueDate): array
    {
        return [
            'id' => self::virtualRecurringId($recurring->id),
            'amount' => (float) $recurring->amount,
            'date' => $dueDate->format('Y-m-d'),
            'description' => $recurring->description,
            'is_private' => false,
            'is_tax_deductible' => false,
            'transfer_id' => null,
            'refund_id' => null,
            'has_refunds' => false,
            'is_fully_refunded' => false,
            'attachments_count' => 0,
            'category' => $recurring->category ? [
                'id' => $recurring->category->id,
                'name' => $recurring->category->name,
                'color' => $recurring->category->color,
                'icon' => $recurring->category->icon,
                'type' => $recurring->category->type,
            ] : null,
            'account' => [
                'id' => $recurring->account->id,
                'name' => $recurring->account->name,
                'currency_code' => $recurring->account->currency_code,
            ],
            'tags' => [],
            'recurring_transaction_id' => $recurring->id,
            'recurring_summary' => [
                'id' => $recurring->id,
                'description' => $recurring->description,
                'frequency' => $recurring->frequency,
            ],
            'investment_id' => null,
            'is_investment' => false,
            'is_pac' => false,
            'pac_summary' => null,
            'is_future' => true,
            'is_virtual' => true,
            'virtual_source' => 'recurring',
            'virtual_source_id' => $recurring->id,
            'projected_balance_after' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPacVirtualRow(InvestmentPac $pac, Carbon $executionDate): array
    {
        $amount = -abs((float) $pac->amount + (float) ($pac->fees ?? 0));
        $assetName = $pac->asset?->name ?? 'PAC';

        return [
            'id' => self::virtualPacId($pac->id),
            'amount' => $amount,
            'date' => $executionDate->format('Y-m-d'),
            'description' => "PAC {$assetName}",
            'is_private' => false,
            'is_tax_deductible' => false,
            'transfer_id' => null,
            'refund_id' => null,
            'has_refunds' => false,
            'is_fully_refunded' => false,
            'attachments_count' => 0,
            'category' => null,
            'account' => $pac->account ? [
                'id' => $pac->account->id,
                'name' => $pac->account->name,
                'currency_code' => $pac->account->currency_code,
            ] : [
                'id' => 0,
                'name' => 'Senza conto collegato',
                'currency_code' => $pac->currency_code,
            ],
            'tags' => [],
            'recurring_transaction_id' => null,
            'recurring_summary' => null,
            'investment_id' => null,
            'is_investment' => true,
            'is_pac' => true,
            'pac_summary' => [
                'id' => $pac->id,
                'asset_name' => $assetName,
            ],
            'is_future' => true,
            'is_virtual' => true,
            'virtual_source' => 'pac',
            'virtual_source_id' => $pac->id,
            'projected_balance_after' => null,
        ];
    }
}
