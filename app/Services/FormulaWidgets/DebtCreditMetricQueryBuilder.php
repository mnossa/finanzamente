<?php

namespace App\Services\FormulaWidgets;

use App\Models\DebtCredit;
use App\Models\User;
use App\Support\FormulaWidgetRuntimeContext;
use App\Support\MetricQueryDefinition;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class DebtCreditMetricQueryBuilder
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
        $query = $this->baseQuery($user);
        $this->applyFilters($query, $definition, $resolvedParameters, $context);

        if ($definition->requiresPeriod()) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                    ->orWhereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('due_date', [$startDate, $endDate]);
            });
        }

        return $this->aggregate($query, $definition);
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
        $query = $this->baseQuery($user);
        $this->applyFilters($query, $definition, $resolvedParameters, $context);

        if ($definition->requiresPeriod()) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                    ->orWhereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('due_date', [$startDate, $endDate]);
            });
        }

        $allowedSort = config('metric_queries.datasources.debts_credits.sort_fields', ['due_date']);
        $sortField = is_array($sort) && in_array($sort['field'] ?? '', $allowedSort, true)
            ? (string) $sort['field']
            : 'due_date';
        $sortDirection = strtolower((string) ($sort['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField === 'amount' ? 'amount' : $sortField, $sortDirection)
            ->limit(max(1, $limit));

        $allowedColumns = config('metric_queries.datasources.debts_credits.list_columns', []);
        $selectedColumns = is_array($columns) && $columns !== []
            ? array_values(array_intersect($columns, $allowedColumns))
            : $allowedColumns;

        return $query->get()->map(function (DebtCredit $dc) use ($selectedColumns) {
            $row = [
                'id' => $dc->id,
                'counterparty' => $dc->counterparty,
                'type' => $dc->type,
                'status' => $dc->status,
                'remaining' => round($dc->getRemainingAmount(), 2),
                'due_date' => $dc->due_date?->format('Y-m-d'),
                'currency' => $dc->currency_code,
            ];

            return array_intersect_key($row, array_flip([...$selectedColumns, 'id']));
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
        $allowed = config('metric_queries.datasources.debts_credits.group_by_fields', []);
        if (! in_array($groupBy, $allowed, true)) {
            return [];
        }

        $query = $this->baseQuery($user);
        $this->applyFilters($query, $definition, $resolvedParameters, $context);

        if ($definition->requiresPeriod()) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                    ->orWhereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('due_date', [$startDate, $endDate]);
            });
        }

        $records = $query->get();
        $buckets = [];

        foreach ($records as $dc) {
            $key = match ($groupBy) {
                'type' => (string) $dc->type,
                'status' => (string) $dc->status,
                'currency' => (string) $dc->currency_code,
                default => 'other',
            };
            $label = $key !== '' ? $key : '—';
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['key' => $key, 'label' => $label, 'items' => []];
            }
            $buckets[$key]['items'][] = $dc;
        }

        $groups = [];
        foreach ($buckets as $bucket) {
            $sub = collect($bucket['items']);
            $value = match ($definition->measure) {
                'count' => (float) $sub->count(),
                'sum_remaining' => round((float) $sub->sum(fn (DebtCredit $dc) => $dc->getRemainingAmount()), 2),
                'sum_initial' => round((float) $sub->sum(fn (DebtCredit $dc) => (float) ($dc->initial_amount ?? $dc->amount)), 2),
                'sum_paid' => round((float) $sub->sum(fn (DebtCredit $dc) => (float) $dc->paid_amount), 2),
                default => (float) $sub->count(),
            };
            $groups[] = [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'value' => $value,
            ];
        }

        usort($groups, fn ($a, $b) => $b['value'] <=> $a['value']);

        return array_slice($groups, 0, max(1, $limit));
    }

    private function baseQuery(User $user): Builder
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            throw ValidationException::withMessages([
                'household' => 'Seleziona una famiglia attiva per calcolare questa metrica.',
            ]);
        }

        return DebtCredit::query()
            ->where('household_id', $householdId)
            ->where(fn ($q) => $q->where('user_id', $user->id));
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
                'type' => $query->where('type', (string) $value),
                'status' => in_array($operator, ['not_in', 'neq'], true)
                    ? $query->where('status', '!=', (string) $value)
                    : $query->where('status', (string) $value),
                'currency' => $query->where('currency_code', (string) $value),
                'counterparty' => $query->where('counterparty', 'like', '%'.(string) $value.'%'),
                default => null,
            };
        }
    }

    private function aggregate(Builder $query, MetricQueryDefinition $definition): float
    {
        $records = (clone $query)->get();

        return match ($definition->measure) {
            'count' => (float) $records->count(),
            'sum_remaining' => round((float) $records->sum(fn (DebtCredit $dc) => $dc->getRemainingAmount()), 2),
            'sum_initial' => round((float) $records->sum(fn (DebtCredit $dc) => (float) ($dc->initial_amount ?? $dc->amount)), 2),
            'sum_paid' => round((float) $records->sum(fn (DebtCredit $dc) => (float) $dc->paid_amount), 2),
            default => 0.0,
        };
    }
}
