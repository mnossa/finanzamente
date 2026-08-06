<?php

namespace App\Services;

use App\Models\Account;
use App\Models\User;
use App\Support\FormulaWidgetRuntimeContext;
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
        private readonly PacProjectionService $pacProjectionService,
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

    public function resolve(
        User $user,
        string $code,
        Carbon $startDate,
        Carbon $endDate,
        ?FormulaWidgetRuntimeContext $context = null,
    ): float {
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
                ->purchasesInPeriod($user, $startDate, $endDate)['amount'],
            'period_stats' => $this->resolvePeriodStatsField($user, $startDate, $endDate, $meta['field'], $context),
            'expense_distribution' => $this->resolveExpenseDistributionField($user, $startDate, $endDate, $meta['field']),
            'linked_investments_at' => $this->investmentLedgerService->linkedInvestedValueAt($user, $endDate),
            'investment_pac_metrics' => $this->resolvePacMetricField($user, $endDate, $meta['field']),
            'context' => $this->contextVariableResolver->resolve($startDate, $endDate, $meta['field']),
            default => throw ValidationException::withMessages([
                'variable_code' => "Resolver non configurato per [{$code}].",
            ]),
        };
    }

    public function resolveForSeries(
        User $user,
        string $code,
        Carbon $bucketEnd,
        ?FormulaWidgetRuntimeContext $context = null,
    ): float {
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

            if (str_starts_with($code, 'pac_')) {
                return $this->resolvePacMetricAt($user, $bucketEnd, $code);
            }

            return $this->resolve($user, $code, $bucketEnd->copy()->startOfDay(), $bucketEnd, $context);
        }

        $bucketStart = $bucketEnd->copy()->startOfMonth()->startOfDay();

        return $this->resolve($user, $code, $bucketStart, $bucketEnd, $context);
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
        $locked = $this->resolveAccountsBalanceAt($user, $asOfDate, lockedOnly: true);
        $linked = $this->investmentLedgerService->linkedInvestedValueAt($user, $asOfDate);

        return round($liquid + $locked + $linked, 2);
    }

    private function resolveLiquidAt(User $user, Carbon $asOfDate): float
    {
        return $this->resolveAccountsBalanceAt($user, $asOfDate, lockedOnly: false);
    }

    /**
     * @param  bool  $lockedOnly  true = solo vincolati; false = solo liquidi (default liquidità)
     */
    private function resolveAccountsBalanceAt(User $user, Carbon $asOfDate, bool $lockedOnly): float
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return 0.0;
        }

        $accounts = Account::query()
            ->where('household_id', $householdId)
            ->where('active', true)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('owner_user_id', $user->id))
            ->get()
            ->filter(fn (Account $account) => $account->isLockedBalance() === $lockedOnly);

        $total = 0.0;

        foreach ($accounts as $account) {
            $sum = (float) $account->transactions()
                ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
                ->whereDate('date', '<=', $asOfDate)
                ->sum('amount');

            $total += (float) $account->initial_balance + $sum;
        }

        return round($total, 2);
    }

    private function resolvePeriodStatsField(
        User $user,
        Carbon $start,
        Carbon $end,
        string $field,
        ?FormulaWidgetRuntimeContext $context = null,
    ): float {
        $accountId = $context?->accountId;
        $stats = $this->dashboardPeriodStatsService->calculate($user, $start, $end, $accountId);

        return (float) ($stats[$field] ?? 0.0);
    }

    private function resolveExpenseDistributionField(User $user, Carbon $start, Carbon $end, string $field): float
    {
        $metrics = $this->expenseDistributionMetricsService->calculate($user, $start, $end);

        return (float) ($metrics[$field] ?? 0.0);
    }

    private function resolvePacMetricField(User $user, Carbon $asOfDate, string $field): float
    {
        return $this->resolvePacMetricAt($user, $asOfDate, match ($field) {
            'monthly_total' => 'pac_monthly_total',
            'ytd_contributions' => 'pac_ytd_contributions',
            'projected_contributions' => 'pac_projected_contributions',
            'projected_patrimonio' => 'pac_projected_patrimonio',
            'active_count' => 'pac_active_count',
            default => 'pac_monthly_total',
        });
    }

    private function resolvePacMetricAt(User $user, Carbon $asOfDate, string $code): float
    {
        $asOf = \Illuminate\Support\Carbon::parse($asOfDate);

        return match ($code) {
            'pac_monthly_total' => $this->pacProjectionService->getMonthlyTotal($user),
            'pac_ytd_contributions' => $this->pacProjectionService->getYtdContributions($user, $asOf),
            'pac_projected_contributions' => $this->pacProjectionService->getProjectedContributions($user, 12, $asOf),
            'pac_projected_patrimonio' => $this->pacProjectionService->getProjectedPatrimonio($user, 12, 0.0, $asOf),
            'pac_active_count' => (float) $this->pacProjectionService->getActivePacCount($user),
            default => 0.0,
        };
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
