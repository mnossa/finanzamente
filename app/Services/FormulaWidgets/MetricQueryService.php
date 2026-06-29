<?php

namespace App\Services\FormulaWidgets;

use App\Models\User;
use App\Support\FormulaWidgetRuntimeContext;
use App\Support\MetricQueryDefinition;
use Carbon\Carbon;

class MetricQueryService
{
    public function __construct(
        private readonly TransactionMetricQueryBuilder $transactionBuilder,
        private readonly DebtCreditMetricQueryBuilder $debtCreditBuilder,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metricQueryRaw
     * @param  array<string, string|int|null>  $resolvedParameters
     */
    public function evaluate(
        User $user,
        ?array $metricQueryRaw,
        Carbon $startDate,
        Carbon $endDate,
        array $resolvedParameters = [],
        ?FormulaWidgetRuntimeContext $context = null,
    ): float {
        $definition = MetricQueryDefinition::fromChartConfig($metricQueryRaw);

        if ($definition === null) {
            return 0.0;
        }

        return match ($definition->datasource) {
            'transactions' => $this->transactionBuilder->evaluate(
                $user,
                $definition,
                $startDate,
                $endDate,
                $resolvedParameters,
                $context,
            ),
            'debts_credits' => $this->debtCreditBuilder->evaluate(
                $user,
                $definition,
                $startDate,
                $endDate,
                $resolvedParameters,
                $context,
            ),
            default => 0.0,
        };
    }

    /**
     * @param  array<string, mixed>|null  $metricQueryRaw
     * @param  array<string, string|int|null>  $resolvedParameters
     * @return array<int, array{label: string, value: float}>
     */
    public function evaluateMonthlySeries(
        User $user,
        ?array $metricQueryRaw,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        array $resolvedParameters = [],
        ?FormulaWidgetRuntimeContext $context = null,
    ): array {
        $definition = MetricQueryDefinition::fromChartConfig($metricQueryRaw);

        if ($definition === null || $definition->datasource !== 'transactions') {
            return [];
        }

        return $this->transactionBuilder->evaluateMonthlySeries(
            $user,
            $definition,
            $rangeStart,
            $rangeEnd,
            $resolvedParameters,
            $context,
        );
    }

    public function hasMetricQuery(?array $chartConfig): bool
    {
        return MetricQueryDefinition::fromChartConfig($chartConfig['metric_query'] ?? null) !== null;
    }
}
