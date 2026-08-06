<?php

namespace App\Services\FormulaWidgets;

use App\Support\MetricQueryDefinition;
use Illuminate\Validation\ValidationException;

class MetricQueryValidator
{
    /**
     * @param  array<string, mixed>|null  $metricQuery
     */
    public function validate(?array $metricQuery): void
    {
        if ($metricQuery === null || $metricQuery === []) {
            return;
        }

        $definition = MetricQueryDefinition::fromChartConfig($metricQuery);

        if ($definition === null) {
            throw ValidationException::withMessages([
                'chart_config' => 'La configurazione metric_query non è valida.',
            ]);
        }

        $datasources = config('metric_queries.datasources', []);

        if (! array_key_exists($definition->datasource, $datasources)) {
            throw ValidationException::withMessages([
                'chart_config' => 'La sorgente dati selezionata non è valida.',
            ]);
        }

        $meta = $datasources[$definition->datasource];
        $allowedMeasures = $meta['measures'] ?? [];

        if (! in_array($definition->measure, $allowedMeasures, true)) {
            throw ValidationException::withMessages([
                'chart_config' => 'La misura selezionata non è valida per questa sorgente.',
            ]);
        }

        $allowedAmountFields = config('metric_queries.amount_fields', ['amount_base', 'amount']);

        if ($definition->datasource === 'transactions'
            && ! in_array($definition->amountField, $allowedAmountFields, true)) {
            throw ValidationException::withMessages([
                'chart_config' => 'Il campo importo non è valido.',
            ]);
        }

        $allowedFilterFields = $meta['filter_fields'] ?? [];
        $allowedOperators = config('metric_queries.operators', []);

        foreach ($definition->filters as $index => $filter) {
            if (! is_array($filter)) {
                throw ValidationException::withMessages([
                    'chart_config' => "Il filtro #{$index} non è valido.",
                ]);
            }

            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? null;

            if (! is_string($field) || ! in_array($field, $allowedFilterFields, true)) {
                throw ValidationException::withMessages([
                    'chart_config' => "Il campo filtro «{$field}» non è consentito.",
                ]);
            }

            if (! is_string($operator) || ! in_array($operator, $allowedOperators, true)) {
                throw ValidationException::withMessages([
                    'chart_config' => "L'operatore filtro «{$operator}» non è consentito.",
                ]);
            }

            $hasRuntime = isset($filter['runtime_key']) && is_string($filter['runtime_key']) && $filter['runtime_key'] !== '';
            $hasValue = array_key_exists('value', $filter) && $filter['value'] !== null && $filter['value'] !== '';

            if (! $hasRuntime && ! $hasValue && ! in_array($operator, ['is_null', 'is_not_null'], true)) {
                throw ValidationException::withMessages([
                    'chart_config' => "Il filtro su «{$field}» richiede un valore o un parametro runtime.",
                ]);
            }
        }
    }
}
