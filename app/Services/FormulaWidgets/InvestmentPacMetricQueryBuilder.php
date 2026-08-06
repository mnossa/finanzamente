<?php

namespace App\Services\FormulaWidgets;

use App\Models\InvestmentPac;
use App\Models\User;
use App\Support\FormulaWidgetRuntimeContext;
use App\Support\MetricQueryDefinition;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class InvestmentPacMetricQueryBuilder
{
    /**
     * @param  array<string, string|int|null>  $resolvedParameters
     */
    public function evaluate(
        User $user,
        MetricQueryDefinition $definition,
        Carbon $startDate,
        Carbon $endDate,
        array $resolvedParameters = [],
        ?FormulaWidgetRuntimeContext $context = null,
    ): float {
        $query = $this->baseQuery($user, $context);
        $this->applyFilters($query, $definition, $resolvedParameters, $context);

        return match ($definition->measure) {
            'count' => (float) (clone $query)->count(),
            'sum' => round((float) (clone $query)->sum('amount'), 2),
            default => 0.0,
        };
    }

    /**
     * @param  array<string, string|int|null>  $resolvedParameters
     * @param  array{field?: string, direction?: string}|null  $sort
     * @param  list<string>|null  $columns
     * @return list<array<string, mixed>>
     */
    public function listRows(
        User $user,
        MetricQueryDefinition $definition,
        Carbon $startDate,
        Carbon $endDate,
        array $resolvedParameters = [],
        ?FormulaWidgetRuntimeContext $context = null,
        int $limit = 10,
        ?array $sort = null,
        ?array $columns = null,
    ): array {
        $query = $this->baseQuery($user, $context);
        $this->applyFilters($query, $definition, $resolvedParameters, $context);

        $allowedSort = config('metric_queries.datasources.investment_pacs.sort_fields', ['start_date']);
        $sortField = is_array($sort) && in_array($sort['field'] ?? '', $allowedSort, true)
            ? (string) $sort['field']
            : 'start_date';
        $sortDirection = strtolower((string) ($sort['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->with(['asset:id,name', 'account:id,name'])
            ->orderBy($sortField, $sortDirection)
            ->limit(max(1, $limit));

        $allowedColumns = config('metric_queries.datasources.investment_pacs.list_columns', []);
        $selectedColumns = is_array($columns) && $columns !== []
            ? array_values(array_intersect($columns, $allowedColumns))
            : $allowedColumns;

        return $query->get()->map(function (InvestmentPac $pac) use ($selectedColumns) {
            $row = [
                'id' => $pac->id,
                'asset' => $pac->asset?->name,
                'amount' => round((float) $pac->amount, 2),
                'frequency' => $pac->frequency,
                'status' => $pac->status,
                'account' => $pac->account?->name,
                'start_date' => $pac->start_date?->format('Y-m-d'),
                'currency' => $pac->currency_code,
            ];

            return array_intersect_key($row, array_flip([...$selectedColumns, 'id', 'currency']));
        })->values()->all();
    }

    /**
     * @param  array<string, string|int|null>  $resolvedParameters
     * @return list<array{key: string, label: string, value: float}>
     */
    public function aggregateGroups(
        User $user,
        MetricQueryDefinition $definition,
        string $groupBy,
        Carbon $startDate,
        Carbon $endDate,
        array $resolvedParameters = [],
        ?FormulaWidgetRuntimeContext $context = null,
        int $limit = 50,
    ): array {
        $allowed = config('metric_queries.datasources.investment_pacs.group_by_fields', []);
        if (! in_array($groupBy, $allowed, true)) {
            return [];
        }

        $query = $this->baseQuery($user, $context);
        $this->applyFilters($query, $definition, $resolvedParameters, $context);

        $measureExpr = match ($definition->measure) {
            'sum' => 'SUM(investment_pacs.amount)',
            default => 'COUNT(*)',
        };

        if ($groupBy === 'asset') {
            $rows = (clone $query)
                ->leftJoin('investment_assets', 'investment_assets.id', '=', 'investment_pacs.investment_asset_id')
                ->selectRaw("COALESCE(investment_assets.id, 0) as group_key, COALESCE(investment_assets.name, 'Senza asset') as group_label, {$measureExpr} as aggregate_value")
                ->groupByRaw('COALESCE(investment_assets.id, 0), COALESCE(investment_assets.name, \'Senza asset\')')
                ->orderByDesc('aggregate_value')
                ->limit(max(1, $limit))
                ->get();
        } elseif ($groupBy === 'account') {
            $rows = (clone $query)
                ->leftJoin('accounts', 'accounts.id', '=', 'investment_pacs.account_id')
                ->selectRaw("COALESCE(accounts.id, 0) as group_key, COALESCE(accounts.name, 'Senza conto') as group_label, {$measureExpr} as aggregate_value")
                ->groupByRaw('COALESCE(accounts.id, 0), COALESCE(accounts.name, \'Senza conto\')')
                ->orderByDesc('aggregate_value')
                ->limit(max(1, $limit))
                ->get();
        } else {
            $column = $groupBy === 'frequency' ? 'frequency' : 'status';
            $rows = (clone $query)
                ->selectRaw("investment_pacs.{$column} as group_key, investment_pacs.{$column} as group_label, {$measureExpr} as aggregate_value")
                ->groupBy("investment_pacs.{$column}")
                ->orderByDesc('aggregate_value')
                ->limit(max(1, $limit))
                ->get();
        }

        return $rows->map(fn ($row) => [
            'key' => (string) $row->group_key,
            'label' => (string) ($row->group_label ?: '—'),
            'value' => round((float) $row->aggregate_value, 2),
        ])->all();
    }

    private function baseQuery(User $user, ?FormulaWidgetRuntimeContext $context): Builder
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            throw ValidationException::withMessages([
                'household' => 'Seleziona una famiglia attiva per calcolare questa metrica.',
            ]);
        }

        $query = InvestmentPac::query()
            ->where('household_id', $householdId);

        $accountId = $context?->accountId;
        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        }

        return $query;
    }

    /**
     * @param  array<string, string|int|null>  $resolvedParameters
     */
    private function applyFilters(
        Builder $query,
        MetricQueryDefinition $definition,
        array $resolvedParameters,
        ?FormulaWidgetRuntimeContext $context,
    ): void {
        foreach ($definition->filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $field = (string) ($filter['field'] ?? '');
            $operator = (string) ($filter['operator'] ?? 'eq');
            $runtimeKey = $filter['runtime_key'] ?? null;
            $value = is_string($runtimeKey) && $runtimeKey !== ''
                ? ($resolvedParameters[$runtimeKey] ?? $context?->getParameter($runtimeKey))
                : ($filter['value'] ?? null);

            if ($value === 'all' || $value === null || $value === '') {
                continue;
            }

            match ($field) {
                'status' => $this->applyScalar($query, 'status', $operator, $value),
                'frequency' => $this->applyScalar($query, 'frequency', $operator, $value),
                'account' => $this->applyScalar($query, 'account_id', $operator, $value),
                'asset' => $this->applyScalar($query, 'investment_asset_id', $operator, $value),
                default => null,
            };
        }
    }

    private function applyScalar(Builder $query, string $column, string $operator, mixed $value): void
    {
        $values = is_array($value)
            ? array_values(array_filter(array_map('strval', $value)))
            : [(string) $value];

        if ($values === []) {
            return;
        }

        if (in_array($operator, ['not_in', 'neq'], true)) {
            $query->whereNotIn($column, $values);
        } else {
            $query->whereIn($column, $values);
        }
    }
}
