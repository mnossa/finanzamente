<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SystemVariableResolver
{
    public function __construct(
        private readonly FinancialMetricsService $financialMetricsService,
        private readonly AccountBalanceService $accountBalanceService,
        private readonly PortfolioSnapshotService $portfolioSnapshotService,
        private readonly InvestmentLedgerService $investmentLedgerService,
        private readonly DashboardPeriodStatsService $dashboardPeriodStatsService,
        private readonly ExpenseDistributionMetricsService $expenseDistributionMetricsService,
        private readonly ContextVariableResolver $contextVariableResolver,
    ) {}

    public function isSystemCode(string $code): bool
    {
        return array_key_exists($code, $this->allVariableDefinitions());
    }

    /**
     * @return array<int, array{code: string, label: string, requires_period: bool, category: string, example: string|null}>
     */
    public function listMetadata(): array
    {
        $examples = config('financial_variables.variable_examples', []);
        $variables = [];

        foreach ($this->allVariableDefinitions() as $code => $meta) {
            $variables[] = [
                'code' => $code,
                'label' => $meta['label'] ?? $code,
                'requires_period' => (bool) ($meta['requires_period'] ?? false),
                'category' => $meta['category'] ?? 'financial',
                'example' => $examples[$code] ?? null,
            ];
        }

        return $variables;
    }

    public function resolve(User $user, string $code, Carbon $startDate, Carbon $endDate): float
    {
        $meta = $this->allVariableDefinitions()[$code] ?? null;

        if ($meta === null) {
            throw ValidationException::withMessages([
                'variable_code' => "La variabile di sistema [{$code}] non esiste.",
            ]);
        }

        if (($meta['requires_period'] ?? false) && $user->active_household_id === null) {
            throw ValidationException::withMessages([
                'household' => 'Seleziona una famiglia attiva per calcolare questa variabile.',
            ]);
        }

        $resolver = $meta['resolver'] ?? null;

        return match ($resolver) {
            'financial_metrics' => $this->resolveFinancialMetricsField($user, $startDate, $endDate, $meta['field']),
            'household_balance' => $this->accountBalanceService->computeHouseholdTotal($user),
            'portfolio_snapshot' => $this->resolvePortfolioField($user, $meta['field']),
            'portfolio_snapshot_at' => $this->resolvePortfolioFieldAt($user, $endDate, $meta['field']),
            'investment_purchases' => $this->investmentLedgerService
                ->unsyncedPurchasesInPeriod($user, $startDate, $endDate)['amount'],
            'period_stats' => $this->resolvePeriodStatsField($user, $startDate, $endDate, $meta['field']),
            'annual_revenue' => $this->resolveAnnualRevenue($user, $startDate, $endDate),
            'revenue_threshold' => $this->resolveRevenueThreshold($user),
            'expense_distribution' => $this->resolveExpenseDistributionField($user, $startDate, $endDate, $meta['field']),
            'linked_investments_at' => $this->investmentLedgerService->linkedInvestedValueAt($user, $endDate),
            'context' => $this->contextVariableResolver->resolve($startDate, $endDate, $meta['field']),
            default => throw ValidationException::withMessages([
                'variable_code' => "Resolver non configurato per [{$code}].",
            ]),
        };
    }

    public function resolveForSeries(User $user, string $code, Carbon $bucketEnd): float
    {
        $meta = $this->allVariableDefinitions()[$code] ?? null;

        if ($meta === null) {
            throw ValidationException::withMessages([
                'variable_code' => "La variabile di sistema [{$code}] non esiste.",
            ]);
        }

        if (! ($meta['requires_period'] ?? false)) {
            if (($meta['resolver'] ?? null) === 'context') {
                return $this->contextVariableResolver->resolve(
                    $bucketEnd->copy()->startOfDay(),
                    $bucketEnd,
                    $meta['field'],
                );
            }

            if ($code === 'patrimonio_total') {
                return $this->resolvePatrimonioAt($user, $bucketEnd);
            }

            if (in_array($code, ['household_balance', 'total_investments', 'investments_linked', 'investments_unlinked'], true)) {
                return $this->resolvePortfolioFieldAt($user, $bucketEnd, match ($code) {
                    'household_balance' => 'liquidValue',
                    'total_investments' => 'investedValue',
                    'investments_linked' => 'investedLinkedValue',
                    'investments_unlinked' => 'investedUnlinkedValue',
                    default => 'totalValue',
                });
            }

            return $this->resolve($user, $code, $bucketEnd->copy()->startOfDay(), $bucketEnd);
        }

        $bucketStart = $bucketEnd->copy()->startOfMonth()->startOfDay();

        return $this->resolve($user, $code, $bucketStart, $bucketEnd);
    }

    private function resolveFinancialMetricsField(User $user, Carbon $start, Carbon $end, string $field): float
    {
        $metrics = $this->financialMetricsService->calculate($user, $start, $end);
        $value = $metrics[$field] ?? 0.0;

        return $value === null ? 0.0 : (float) $value;
    }

    private function resolvePortfolioField(User $user, string $field): float
    {
        $snapshot = $this->portfolioSnapshotService->build($user);

        return (float) ($snapshot[$field] ?? 0.0);
    }

    private function resolvePortfolioFieldAt(User $user, Carbon $asOfDate, string $field): float
    {
        if ($field === 'liquidValue' || $field === 'household_balance') {
            return $this->resolveLiquidAt($user, $asOfDate);
        }

        if (in_array($field, ['investedValue', 'investedLinkedValue', 'investedUnlinkedValue'], true)) {
            return match ($field) {
                'investedLinkedValue' => $this->investmentLedgerService->linkedInvestedValueAt($user, $asOfDate),
                default => $this->resolvePortfolioField($user, $field),
            };
        }

        return $this->resolvePatrimonioAt($user, $asOfDate);
    }

    private function resolvePatrimonioAt(User $user, Carbon $asOfDate): float
    {
        $liquid = $this->resolveLiquidAt($user, $asOfDate);
        $linked = $this->investmentLedgerService->linkedInvestedValueAt($user, $asOfDate);

        return round($liquid + $linked, 2);
    }

    private function resolveLiquidAt(User $user, Carbon $asOfDate): float
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return 0.0;
        }

        $accounts = Account::query()
            ->where('household_id', $householdId)
            ->where('active', true)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('owner_user_id', $user->id))
            ->get();

        $total = 0.0;

        foreach ($accounts as $account) {
            $sum = (float) $account->transactions()
                ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
                ->where('date', '<=', $asOfDate->toDateString())
                ->sum('amount');

            $total += (float) $account->initial_balance + $sum;
        }

        return round($total, 2);
    }

    private function resolvePeriodStatsField(User $user, Carbon $start, Carbon $end, string $field): float
    {
        $stats = $this->dashboardPeriodStatsService->calculate($user, $start, $end);

        return (float) ($stats[$field] ?? 0.0);
    }

    private function resolveAnnualRevenue(User $user, Carbon $start, Carbon $end): float
    {
        if ($user->user_type !== 'partita_iva') {
            return 0.0;
        }

        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return 0.0;
        }

        return (float) Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where('user_id', $user->id)
            ->where('amount', '>', 0)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');
    }

    private function resolveRevenueThreshold(User $user): float
    {
        $settings = $user->profile_settings ?? [];

        return (float) ($settings['revenue_threshold'] ?? 85000);
    }

    private function resolveExpenseDistributionField(User $user, Carbon $start, Carbon $end, string $field): float
    {
        $metrics = $this->expenseDistributionMetricsService->calculate($user, $start, $end);

        return (float) ($metrics[$field] ?? 0.0);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function allVariableDefinitions(): array
    {
        return array_merge(
            config('financial_variables.system_variables', []),
            config('financial_variables.context_variables', []),
        );
    }
}
