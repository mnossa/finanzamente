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
