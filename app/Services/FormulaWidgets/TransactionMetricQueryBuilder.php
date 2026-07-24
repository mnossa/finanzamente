<?php

namespace App\Services\FormulaWidgets;

use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FormulaPeriodResolver;
use App\Support\FormulaWidgetRuntimeContext;
use App\Support\MetricQueryDefinition;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class TransactionMetricQueryBuilder
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
        $query = $this->baseQuery($user, $startDate, $endDate, $context);
        $this->applyFilters($query, $user, $definition, $resolvedParameters, $context);

        return $this->aggregate($query, $definition);
    }

    /**
     * @param  array<string, string|int|null>  $resolvedParameters
     * @return array<int, array{label: string, value: float}>
     */
    public function evaluateMonthlySeries(
        User $user,
        MetricQueryDefinition $definition,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        array $resolvedParameters = [],
        ?FormulaWidgetRuntimeContext $context = null,
    ): array {
        $buckets = app(FormulaPeriodResolver::class)->monthBuckets($rangeStart, $rangeEnd);
        $series = [];

        foreach ($buckets as $bucket) {
            $value = $this->evaluate(
                $user,
                $definition,
                $bucket['start'],
                $bucket['end'],
                $resolvedParameters,
                $context,
            );

            $series[] = [
                'label' => $bucket['label'],
                'value' => round($value, 2),
            ];
        }

        return $series;
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
        $query = $this->baseQuery($user, $startDate, $endDate, $context);
        $this->applyFilters($query, $user, $definition, $resolvedParameters, $context);

        $allowedSort = config('metric_queries.datasources.transactions.sort_fields', ['date']);
        $sortField = is_array($sort) && in_array($sort['field'] ?? '', $allowedSort, true)
            ? (string) $sort['field']
            : 'date';
        $sortDirection = strtolower((string) ($sort['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->with(['category:id,name,color,icon', 'account:id,name,currency_code'])
            ->orderBy($sortField === 'amount' ? 'amount' : ($sortField === 'description' ? 'description' : 'date'), $sortDirection)
            ->limit(max(1, $limit));

        $allowedColumns = config('metric_queries.datasources.transactions.list_columns', []);
        $selectedColumns = is_array($columns) && $columns !== []
            ? array_values(array_intersect($columns, $allowedColumns))
            : $allowedColumns;

        if ($selectedColumns === []) {
            $selectedColumns = $allowedColumns;
        }

        return $query->get()->map(function (Transaction $tx) use ($selectedColumns, $definition) {
            $amountCol = $definition->amountField === 'amount' ? (float) $tx->amount : (float) ($tx->amount_base ?? $tx->amount);
            $row = [
                'id' => $tx->id,
                'date' => $tx->date?->format('Y-m-d'),
                'description' => $tx->description,
                'amount' => round($amountCol, 2),
                'category' => $tx->category?->name,
                'account' => $tx->account?->name,
                'currency' => $tx->account?->currency_code ?? $tx->currency_code,
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
        $allowed = config('metric_queries.datasources.transactions.group_by_fields', []);
        if (! in_array($groupBy, $allowed, true)) {
            return [];
        }

        $query = $this->baseQuery($user, $startDate, $endDate, $context);
        $this->applyFilters($query, $user, $definition, $resolvedParameters, $context);

        $amountCol = $definition->amountField === 'amount' ? 'transactions.amount' : 'transactions.amount_base';
        $measureExpr = match ($definition->measure) {
            'count' => 'COUNT(*)',
            'sum' => "SUM({$amountCol})",
            'sum_abs' => "SUM(ABS({$amountCol}))",
            'avg' => "AVG({$amountCol})",
            'min' => "MIN({$amountCol})",
            'max' => "MAX({$amountCol})",
            'net' => "SUM({$amountCol})",
            default => 'COUNT(*)',
        };

        if ($groupBy === 'tag') {
            $rows = (clone $query)
                ->join('transaction_tag', 'transactions.id', '=', 'transaction_tag.transaction_id')
                ->join('tags', 'tags.id', '=', 'transaction_tag.tag_id')
                ->selectRaw("tags.id as group_key, tags.name as group_label, {$measureExpr} as aggregate_value")
                ->groupBy('tags.id', 'tags.name')
                ->orderByDesc('aggregate_value')
                ->limit(max(1, $limit))
                ->get();

            return $rows->map(fn ($row) => [
                'key' => (string) $row->group_key,
                'label' => (string) ($row->group_label ?: 'Senza nome'),
                'value' => round((float) $row->aggregate_value, 2),
            ])->all();
        }

        if ($groupBy === 'category') {
            $rows = (clone $query)
                ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
                ->selectRaw("COALESCE(categories.id, 0) as group_key, COALESCE(categories.name, 'Senza categoria') as group_label, {$measureExpr} as aggregate_value")
                ->groupByRaw('COALESCE(categories.id, 0), COALESCE(categories.name, \'Senza categoria\')')
                ->orderByDesc('aggregate_value')
                ->limit(max(1, $limit))
                ->get();
        } elseif ($groupBy === 'account') {
            $rows = (clone $query)
                ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
                ->selectRaw("accounts.id as group_key, accounts.name as group_label, {$measureExpr} as aggregate_value")
                ->groupBy('accounts.id', 'accounts.name')
                ->orderByDesc('aggregate_value')
                ->limit(max(1, $limit))
                ->get();
        } elseif ($groupBy === 'currency') {
            $rows = (clone $query)
                ->selectRaw("transactions.currency_code as group_key, transactions.currency_code as group_label, {$measureExpr} as aggregate_value")
                ->groupBy('transactions.currency_code')
                ->orderByDesc('aggregate_value')
                ->limit(max(1, $limit))
                ->get();
        } else {
            // transaction_type
            $expenseQ = (clone $query)->where('transactions.amount', '<', 0);
            $incomeQ = (clone $query)->where('transactions.amount', '>', 0);
            $groups = [];
            $expenseVal = $this->aggregate($expenseQ, $definition);
            $incomeVal = $this->aggregate($incomeQ, $definition);
            if ($expenseVal != 0.0) {
                $groups[] = ['key' => 'expense', 'label' => 'Uscite', 'value' => $expenseVal];
            }
            if ($incomeVal != 0.0) {
                $groups[] = ['key' => 'income', 'label' => 'Entrate', 'value' => $incomeVal];
            }
            usort($groups, fn ($a, $b) => $b['value'] <=> $a['value']);

            return array_slice($groups, 0, max(1, $limit));
        }

        return $rows->map(fn ($row) => [
            'key' => (string) $row->group_key,
            'label' => (string) ($row->group_label ?: '—'),
            'value' => round((float) $row->aggregate_value, 2),
        ])->all();
    }

    private function baseQuery(
        User $user,
        Carbon $startDate,
        Carbon $endDate,
        ?FormulaWidgetRuntimeContext $context,
    ): Builder {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            throw ValidationException::withMessages([
                'household' => 'Seleziona una famiglia attiva per calcolare questa metrica.',
            ]);
        }

        $effectiveEnd = $endDate->copy();
        if ($effectiveEnd->gt(Carbon::today())) {
            $effectiveEnd = Carbon::today();
        }

        $accountId = $context?->accountId;

        $query = Transaction::query()
            ->whereHas('account', function ($q) use ($householdId, $user, $accountId) {
                $q->where('household_id', $householdId)
                    ->where('active', true);

                if ($accountId !== null) {
                    $q->where('id', $accountId)
                        ->where(fn ($sub) => $sub->where('is_private', false)
                            ->orWhere('owner_user_id', $user->id));
                }
            })
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->whereBetween('date', [$startDate, $effectiveEnd])
            ->whereNull('transfer_id')
            ->excludeInterHouseholdStats();

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
        User $user,
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
            $value = $this->resolveFilterValue($filter, $resolvedParameters, $context);

            match ($field) {
                'tag' => $this->applyTagFilter($query, $operator, $value, $user),
                'category' => $this->applyCategoryFilter($query, $operator, $value),
                'currency' => $this->applyScalarFilter($query, 'currency_code', $operator, $value),
                'account' => $this->applyScalarFilter($query, 'account_id', $operator, $value),
                'debt_credit' => $this->applyScalarFilter($query, 'debt_credit_id', $operator, $value),
                'transaction_type' => $this->applyTransactionTypeFilter($query, $operator, $value),
                'tax_deductible' => $this->applyBooleanFilter($query, 'is_tax_deductible', $operator, $value),
                'amount_min' => $this->applyAmountThreshold($query, $definition->amountField, '>=', $value),
                'amount_max' => $this->applyAmountThreshold($query, $definition->amountField, '<=', $value),
                'has_tag' => $this->applyHasTagFilter($query, $operator, $value),
                'is_private' => $this->applyBooleanFilter($query, 'is_private', $operator, $value),
                'is_split' => $this->applySplitFilter($query, $operator, $value),
                default => null,
            };
        }
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function resolveFilterValue(
        array $filter,
        array $resolvedParameters,
        ?FormulaWidgetRuntimeContext $context,
    ): mixed {
        $runtimeKey = $filter['runtime_key'] ?? null;

        if (is_string($runtimeKey) && $runtimeKey !== '') {
            if (array_key_exists($runtimeKey, $resolvedParameters)) {
                return $resolvedParameters[$runtimeKey];
            }

            return $context?->getParameter($runtimeKey);
        }

        return $filter['value'] ?? null;
    }

    private function applyTagFilter(Builder $query, string $operator, mixed $value, User $user): void
    {
        if ($this->isAllValue($value)) {
            return;
        }

        $tagIds = $this->normalizeIds($value);

        if ($tagIds === []) {
            return;
        }

        $householdId = $user->active_household_id;

        $allowedTagIds = Tag::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->whereIn('id', $tagIds)
            ->pluck('id')
            ->all();

        if ($allowedTagIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $closure = fn ($q) => $q->whereIn('tags.id', $allowedTagIds);

        if (in_array($operator, ['not_in', 'neq'], true)) {
            $query->whereDoesntHave('tags', $closure);
        } else {
            $query->whereHas('tags', $closure);
        }
    }

    private function applyCategoryFilter(Builder $query, string $operator, mixed $value): void
    {
        if ($this->isAllValue($value) || $this->isNoneValue($value)) {
            if ($this->isNoneValue($value) && in_array($operator, ['not_in', 'neq'], true)) {
                return;
            }

            if ($this->isNoneValue($value)) {
                return;
            }

            return;
        }

        $categoryIds = $this->normalizeIds($value);

        if ($categoryIds === []) {
            return;
        }

        if (in_array($operator, ['not_in', 'neq'], true)) {
            $query->where(function ($q) use ($categoryIds) {
                $q->whereNull('category_id')
                    ->orWhereNotIn('category_id', $categoryIds);
            });
        } else {
            $query->whereIn('category_id', $categoryIds);
        }
    }

    private function applyTransactionTypeFilter(Builder $query, string $operator, mixed $value): void
    {
        if ($this->isAllValue($value)) {
            return;
        }

        $type = is_array($value) ? ($value[0] ?? 'all') : (string) $value;

        if ($type === 'income') {
            $query->where('amount', '>', 0);
        } elseif ($type === 'expense') {
            $query->where('amount', '<', 0);
        }
    }

    private function applyBooleanFilter(Builder $query, string $column, string $operator, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($bool === null) {
            return;
        }

        if (in_array($operator, ['neq', 'not_in'], true)) {
            $query->where($column, '!=', $bool);
        } else {
            $query->where($column, $bool);
        }
    }

    private function applyScalarFilter(Builder $query, string $column, string $operator, mixed $value): void
    {
        if ($this->isAllValue($value)) {
            return;
        }

        $values = $this->normalizeIds($value);

        if ($values === []) {
            $scalar = is_scalar($value) ? $value : null;

            if ($scalar === null) {
                return;
            }

            $values = [(int) $scalar];
        }

        if (in_array($operator, ['not_in', 'neq'], true)) {
            $query->whereNotIn($column, $values);
        } else {
            $query->whereIn($column, $values);
        }
    }

    private function applyAmountThreshold(Builder $query, string $amountField, string $operator, mixed $value): void
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return;
        }

        $column = $amountField === 'amount' ? 'amount' : 'amount_base';
        $query->whereRaw("ABS({$column}) {$operator} ?", [(float) $value]);
    }

    private function applyHasTagFilter(Builder $query, string $operator, mixed $value): void
    {
        $shouldHave = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

        if (in_array($operator, ['neq', 'not_in'], true)) {
            $shouldHave = ! $shouldHave;
        }

        if ($shouldHave) {
            $query->whereHas('tags');
        } else {
            $query->whereDoesntHave('tags');
        }
    }

    private function applySplitFilter(Builder $query, string $operator, mixed $value): void
    {
        $isSplit = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($isSplit === null) {
            return;
        }

        if ($isSplit) {
            $query->whereNotNull('split_group_id');
        } else {
            $query->whereNull('split_group_id');
        }
    }

    private function aggregate(Builder $query, MetricQueryDefinition $definition): float
    {
        $amountCol = $definition->amountField === 'amount' ? 'amount' : 'amount_base';

        return match ($definition->measure) {
            'count' => (float) (clone $query)->count(),
            'sum' => round((float) (clone $query)->sum($amountCol), 2),
            'sum_abs' => round((float) (clone $query)->selectRaw("SUM(ABS({$amountCol})) as aggregate")->value('aggregate'), 2),
            'avg' => round((float) (clone $query)->avg($amountCol), 2),
            'min' => round((float) (clone $query)->min($amountCol), 2),
            'max' => round((float) (clone $query)->max($amountCol), 2),
            'net' => $this->aggregateNet($query, $amountCol),
            default => 0.0,
        };
    }

    private function aggregateNet(Builder $query, string $amountCol): float
    {
        $income = (float) (clone $query)->where($amountCol === 'amount' ? 'amount' : 'amount_base', '>', 0)
            ->sum($amountCol);
        $expenses = abs((float) (clone $query)->where($amountCol === 'amount' ? 'amount' : 'amount_base', '<', 0)
            ->sum($amountCol));

        return round($income - $expenses, 2);
    }

    private function isAllValue(mixed $value): bool
    {
        return $value === 'all' || $value === null || $value === '' || $value === [];
    }

    private function isNoneValue(mixed $value): bool
    {
        return $value === 'none';
    }

    /**
     * @return list<int>
     */
    private function normalizeIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }

        if (is_numeric($value)) {
            return [(int) $value];
        }

        if (is_string($value) && str_contains($value, ',')) {
            return array_values(array_filter(array_map('intval', explode(',', $value))));
        }

        return [];
    }
}
