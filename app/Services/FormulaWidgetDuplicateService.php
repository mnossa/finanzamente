<?php

namespace App\Services;

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;

class FormulaWidgetDuplicateService
{
    /**
     * Cerca un widget dell'utente con la stessa formula e la stessa configurazione grafica.
     */
    public function findDuplicate(
        User $user,
        FinancialVariable $variable,
        string $displayType,
        ?string $periodPreset,
        ?array $chartConfig,
        ?int $excludeWidgetId = null,
    ): ?FormulaWidget {
        $targetFingerprint = $this->buildFingerprint($variable, $displayType, $periodPreset, $chartConfig);

        return FormulaWidget::query()
            ->where('user_id', $user->id)
            ->where('is_official_template', false)
            ->when($excludeWidgetId !== null, fn ($query) => $query->where('id', '!=', $excludeWidgetId))
            ->with('financialVariable')
            ->get()
            ->first(fn (FormulaWidget $widget) => $widget->financialVariable !== null
                && $this->buildFingerprint(
                    $widget->financialVariable,
                    $widget->display_type,
                    $widget->period_preset,
                    $widget->chart_config,
                ) === $targetFingerprint);
    }

    public function findDuplicateByVariableId(
        User $user,
        int $financialVariableId,
        string $displayType,
        ?string $periodPreset,
        ?array $chartConfig,
        ?int $excludeWidgetId = null,
    ): ?FormulaWidget {
        $variable = FinancialVariable::query()
            ->where('user_id', $user->id)
            ->find($financialVariableId);

        if ($variable === null) {
            return null;
        }

        return $this->findDuplicate($user, $variable, $displayType, $periodPreset, $chartConfig, $excludeWidgetId);
    }

    /**
     * Cerca un widget equivalente nella galleria (template ufficiale o community pubblico).
     */
    public function findMarketplaceEquivalent(
        User $user,
        FinancialVariable $variable,
        string $displayType,
        ?string $periodPreset,
        ?array $chartConfig,
    ): ?FormulaWidget {
        $targetFingerprint = $this->buildFingerprint($variable, $displayType, $periodPreset, $chartConfig);
        $retiredSlugs = config('financial_variables.retired_official_template_slugs', []);

        return FormulaWidget::query()
            ->where('is_public', true)
            ->where(function ($query) use ($user) {
                $query->where('is_official_template', true)
                    ->orWhere('user_id', '!=', $user->id);
            })
            ->when($retiredSlugs !== [], function ($query) use ($retiredSlugs) {
                $query->where(function ($inner) use ($retiredSlugs) {
                    $inner->where('is_official_template', false)
                        ->orWhereNotIn('template_slug', $retiredSlugs);
                });
            })
            ->with('financialVariable')
            ->orderByDesc('is_official_template')
            ->orderBy('name')
            ->get()
            ->first(fn (FormulaWidget $widget) => $widget->financialVariable !== null
                && $this->buildFingerprint(
                    $widget->financialVariable,
                    $widget->display_type,
                    $widget->period_preset,
                    $widget->chart_config,
                ) === $targetFingerprint);
    }

    public function findMarketplaceEquivalentByVariableId(
        User $user,
        int $financialVariableId,
        string $displayType,
        ?string $periodPreset,
        ?array $chartConfig,
    ): ?FormulaWidget {
        $variable = FinancialVariable::query()
            ->where('user_id', $user->id)
            ->find($financialVariableId);

        if ($variable === null) {
            return null;
        }

        return $this->findMarketplaceEquivalent($user, $variable, $displayType, $periodPreset, $chartConfig);
    }

    public function buildFingerprint(
        FinancialVariable $variable,
        string $displayType,
        ?string $periodPreset,
        ?array $chartConfig,
    ): string {
        $payload = [
            'variable' => $this->variableSignature($variable),
            'display_type' => $displayType,
            'period_preset' => $this->normalizePeriodPreset($periodPreset),
            'chart' => $this->normalizeChartConfig($displayType, $chartConfig),
        ];

        return hash('xxh128', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function variableSignature(FinancialVariable $variable): string
    {
        if ($variable->isStatic()) {
            return 'static:'.number_format((float) $variable->static_value, 2, '.', '');
        }

        return 'formula:'.$this->normalizeFormulaString((string) $variable->formula_string);
    }

    private function normalizeFormulaString(string $formula): string
    {
        $collapsed = preg_replace('/\s+/', '', trim($formula));

        return $collapsed ?? '';
    }

    private function normalizePeriodPreset(?string $periodPreset): ?string
    {
        if ($periodPreset === null || $periodPreset === '') {
            return null;
        }

        return $periodPreset;
    }

    /**
     * Normalizza chart_config tenendo solo i campi che definiscono il comportamento del grafico.
     *
     * @param  array<string, mixed>|null  $chartConfig
     * @return array<string, mixed>
     */
    private function normalizeChartConfig(string $displayType, ?array $chartConfig): array
    {
        $chartConfig = $chartConfig ?? [];

        return match ($displayType) {
            FormulaWidget::DISPLAY_KPI => array_filter([
                'show_delta' => (bool) ($chartConfig['show_delta'] ?? false),
                'format' => (string) ($chartConfig['format'] ?? 'currency'),
                'variant' => is_string($chartConfig['variant'] ?? null) ? $chartConfig['variant'] : null,
                'metric_query' => $this->normalizeMetricQuery($chartConfig['metric_query'] ?? null),
                'parameters' => $this->normalizeParameters($chartConfig['parameters'] ?? []),
            ], fn ($value) => $value !== null && $value !== []),
            FormulaWidget::DISPLAY_PROGRESS => array_filter([
                'value_code' => (string) ($chartConfig['value_code'] ?? ''),
                'threshold_code' => isset($chartConfig['threshold_code']) ? (string) $chartConfig['threshold_code'] : null,
                'threshold_amount' => isset($chartConfig['threshold_amount']) && is_numeric($chartConfig['threshold_amount'])
                    ? (float) $chartConfig['threshold_amount']
                    : null,
                'variant' => is_string($chartConfig['variant'] ?? null) ? $chartConfig['variant'] : null,
                'bands' => is_array($chartConfig['bands'] ?? null) ? $chartConfig['bands'] : null,
                'parameters' => $this->normalizeParameters($chartConfig['parameters'] ?? []),
            ], fn ($value) => $value !== null && $value !== []),
            FormulaWidget::DISPLAY_LINE,
            FormulaWidget::DISPLAY_AREA => array_filter([
                'variant' => is_string($chartConfig['variant'] ?? null) ? $chartConfig['variant'] : null,
            ], fn ($value) => $value !== null),
            FormulaWidget::DISPLAY_BAR,
            FormulaWidget::DISPLAY_HORIZONTAL_BAR,
            FormulaWidget::DISPLAY_PIE,
            FormulaWidget::DISPLAY_TREEMAP,
            FormulaWidget::DISPLAY_STACKED_BAR => [
                'series' => $this->normalizeSeries($chartConfig['series'] ?? []),
                'variant' => is_string($chartConfig['variant'] ?? null) ? $chartConfig['variant'] : null,
            ],
            FormulaWidget::DISPLAY_TABLE => array_filter([
                'metric_query' => $this->normalizeMetricQuery($chartConfig['metric_query'] ?? null),
                'table' => $this->normalizeTableConfig($chartConfig['table'] ?? null),
                'parameters' => $this->normalizeParameters($chartConfig['parameters'] ?? []),
            ], fn ($value) => $value !== null && $value !== []),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>|null  $table
     * @return array<string, mixed>|null
     */
    private function normalizeTableConfig(mixed $table): ?array
    {
        if (! is_array($table) || ! isset($table['mode'])) {
            return null;
        }

        $mode = ($table['mode'] === 'aggregate') ? 'aggregate' : 'rows';
        $normalized = ['mode' => $mode];

        if (isset($table['row_limit']) && is_numeric($table['row_limit'])) {
            $normalized['row_limit'] = (int) $table['row_limit'];
        }

        if (isset($table['group_by']) && is_string($table['group_by']) && $table['group_by'] !== '') {
            $normalized['group_by'] = $table['group_by'];
        }

        if (isset($table['columns']) && is_array($table['columns'])) {
            $normalized['columns'] = array_values(array_filter($table['columns'], 'is_string'));
        }

        if (isset($table['sort']) && is_array($table['sort']) && isset($table['sort']['field'])) {
            $normalized['sort'] = [
                'field' => (string) $table['sort']['field'],
                'direction' => (($table['sort']['direction'] ?? 'desc') === 'asc') ? 'asc' : 'desc',
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{code: string}>
     */
    private function normalizeSeries(mixed $series): array
    {
        if (! is_array($series)) {
            return [];
        }

        $normalized = [];
        foreach ($series as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $code = $entry['code'] ?? null;
            if (! is_string($code) || $code === '') {
                continue;
            }

            $normalized[] = ['code' => $code];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $metricQuery
     * @return array<string, mixed>|null
     */
    private function normalizeMetricQuery(mixed $metricQuery): ?array
    {
        if (! is_array($metricQuery) || ! isset($metricQuery['datasource'], $metricQuery['measure'])) {
            return null;
        }

        $filters = is_array($metricQuery['filters'] ?? null) ? $metricQuery['filters'] : [];
        $normalizedFilters = [];

        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $field = $filter['field'] ?? null;
            $operator = $filter['operator'] ?? null;
            if (! is_string($field) || $field === '' || ! is_string($operator) || $operator === '') {
                continue;
            }

            $entry = [
                'field' => $field,
                'operator' => $operator,
            ];

            if (array_key_exists('value', $filter)) {
                $entry['value'] = $filter['value'];
            }

            $runtimeKey = $filter['runtime_key'] ?? null;
            if (is_string($runtimeKey) && $runtimeKey !== '') {
                $entry['runtime_key'] = $runtimeKey;
            }

            $normalizedFilters[] = $entry;
        }

        usort($normalizedFilters, function (array $left, array $right): int {
            return strcmp(
                json_encode($left, JSON_THROW_ON_ERROR),
                json_encode($right, JSON_THROW_ON_ERROR),
            );
        });

        return [
            'datasource' => (string) $metricQuery['datasource'],
            'measure' => (string) $metricQuery['measure'],
            'amount_field' => (string) ($metricQuery['amount_field'] ?? 'amount_base'),
            'filters' => $normalizedFilters,
        ];
    }

    /**
     * @return list<array{key: string, type: string, default?: string}>
     */
    private function normalizeParameters(mixed $parameters): array
    {
        if (! is_array($parameters)) {
            return [];
        }

        $normalized = [];

        foreach ($parameters as $parameter) {
            if (! is_array($parameter)) {
                continue;
            }

            $key = $parameter['key'] ?? null;
            $type = $parameter['type'] ?? null;
            if (! is_string($key) || $key === '' || ! is_string($type) || $type === '') {
                continue;
            }

            $entry = [
                'key' => $key,
                'type' => $type,
            ];

            $default = $parameter['default'] ?? null;
            if (is_string($default) && $default !== '') {
                $entry['default'] = $default;
            }

            $normalized[] = $entry;
        }

        usort($normalized, fn (array $left, array $right): int => strcmp($left['key'], $right['key']));

        return $normalized;
    }
}
