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
