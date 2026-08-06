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
        private readonly InvestmentPacMetricQueryBuilder $investmentPacBuilder,
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
            'investment_pacs' => $this->investmentPacBuilder->evaluate(
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

    /**
     * @param  array<string, mixed>|null  $metricQueryRaw
     * @param  array<string, string|int|null>  $resolvedParameters
     * @param  array{field?: string, direction?: string}|null  $sort
     * @param  list<string>|null  $columns
     * @return list<array<string, mixed>>
     */
    public function listRows(
        User $user,
        ?array $metricQueryRaw,
        Carbon $startDate,
        Carbon $endDate,
        array $resolvedParameters = [],
        ?FormulaWidgetRuntimeContext $context = null,
        int $limit = 10,
        ?array $sort = null,
        ?array $columns = null,
    ): array {
        $definition = MetricQueryDefinition::fromChartConfig($metricQueryRaw);

        if ($definition === null) {
            return [];
        }

        $args = [$user, $definition, $startDate, $endDate, $resolvedParameters, $context, $limit, $sort, $columns];

        return match ($definition->datasource) {
            'transactions' => $this->transactionBuilder->listRows(...$args),
            'debts_credits' => $this->debtCreditBuilder->listRows(...$args),
            'investment_pacs' => $this->investmentPacBuilder->listRows(...$args),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>|null  $metricQueryRaw
     * @param  array<string, string|int|null>  $resolvedParameters
     * @return list<array{key: string, label: string, value: float}>
     */
    public function aggregateTable(
        User $user,
        ?array $metricQueryRaw,
        string $groupBy,
        Carbon $startDate,
        Carbon $endDate,
        array $resolvedParameters = [],
        ?FormulaWidgetRuntimeContext $context = null,
        int $limit = 50,
    ): array {
        $definition = MetricQueryDefinition::fromChartConfig($metricQueryRaw);

        if ($definition === null) {
            return [];
        }

        $args = [$user, $definition, $groupBy, $startDate, $endDate, $resolvedParameters, $context, $limit];

        return match ($definition->datasource) {
            'transactions' => $this->transactionBuilder->aggregateGroups(...$args),
            'debts_credits' => $this->debtCreditBuilder->aggregateGroups(...$args),
            'investment_pacs' => $this->investmentPacBuilder->aggregateGroups(...$args),
            default => [],
        };
    }

    public function hasMetricQuery(?array $chartConfig): bool
    {
        return MetricQueryDefinition::fromChartConfig($chartConfig['metric_query'] ?? null) !== null;
    }
}
