<?php

namespace App\Services;

use App\Models\FormulaWidget;
use App\Services\FormulaWidgets\MetricQueryValidator;
use App\Support\FormulaTokenParser;
use Illuminate\Validation\ValidationException;

class FormulaWidgetConfigValidator
{
    public function __construct(
        private readonly FormulaTokenParser $tokenParser,
        private readonly SystemVariableResolver $systemVariableResolver,
        private readonly FormulaWidgetParameterService $parameterService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $chartConfig
     */
    public function validate(
        string $displayType,
        ?string $periodPreset,
        ?array $chartConfig,
        ?string $formulaString = null,
        bool $isPublic = false,
    ): void {
        if (! in_array($displayType, FormulaWidget::displayTypes(), true)) {
            throw ValidationException::withMessages([
                'display_type' => 'Il tipo di visualizzazione non è valido.',
            ]);
        }

        $requiresPeriod = in_array($displayType, [
            FormulaWidget::DISPLAY_LINE,
            FormulaWidget::DISPLAY_AREA,
            FormulaWidget::DISPLAY_STACKED_BAR,
            FormulaWidget::DISPLAY_PROGRESS,
            FormulaWidget::DISPLAY_TABLE,
        ], true) || ($chartConfig['show_delta'] ?? false);

        // Table with non-period datasource (PAC / debts) can omit period.
        if ($displayType === FormulaWidget::DISPLAY_TABLE) {
            $mqDatasource = $chartConfig['metric_query']['datasource'] ?? 'transactions';
            $requiresPeriod = (bool) (config("metric_queries.datasources.{$mqDatasource}.requires_period") ?? true);
        }

        if ($requiresPeriod && ($periodPreset === null || $periodPreset === '')) {
            throw ValidationException::withMessages([
                'period_preset' => 'Il periodo è obbligatorio per questo tipo di widget.',
            ]);
        }

        if ($periodPreset !== null && $periodPreset !== '' && ! array_key_exists($periodPreset, config('financial_variables.period_presets', []))) {
            throw ValidationException::withMessages([
                'period_preset' => 'Il periodo selezionato non è valido.',
            ]);
        }

        match ($displayType) {
            FormulaWidget::DISPLAY_BAR,
            FormulaWidget::DISPLAY_HORIZONTAL_BAR,
            FormulaWidget::DISPLAY_PIE,
            FormulaWidget::DISPLAY_TREEMAP => $this->validateSeries($chartConfig ?? [], 2),
            FormulaWidget::DISPLAY_STACKED_BAR => $this->validateSeries($chartConfig ?? [], 2),
            FormulaWidget::DISPLAY_PROGRESS => $this->validateProgressConfig($chartConfig ?? []),
            FormulaWidget::DISPLAY_TABLE => $this->validateTableConfig($chartConfig ?? []),
            default => null,
        };

        app(FormulaWidgetParameterService::class)->validateChartConfig($chartConfig, $isPublic);

        if ($chartConfig !== null) {
            app(MetricQueryValidator::class)
                ->validate($chartConfig['metric_query'] ?? null);
        }

        if ($formulaString !== null) {
            foreach ($this->tokenParser->extract($formulaString) as $code) {
                $this->assertResolvableCode($code);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $chartConfig
     */
    private function validateSeries(array $chartConfig, int $minSeries): void
    {
        $series = $chartConfig['series'] ?? [];

        if (! is_array($series) || count($series) < $minSeries) {
            throw ValidationException::withMessages([
                'chart_config' => "Servono almeno {$minSeries} serie per questo grafico.",
            ]);
        }

        foreach ($series as $entry) {
            $code = $entry['code'] ?? null;
            if (! is_string($code) || $code === '') {
                throw ValidationException::withMessages([
                    'chart_config' => 'Ogni serie deve avere un codice variabile.',
                ]);
            }

            $this->assertResolvableCode($code);
        }
    }

    /**
     * @param  array<string, mixed>  $chartConfig
     */
    private function validateProgressConfig(array $chartConfig): void
    {
        $valueCode = $chartConfig['value_code'] ?? null;
        if (! is_string($valueCode) || $valueCode === '') {
            throw ValidationException::withMessages([
                'chart_config' => 'La configurazione di avanzamento richiede value_code.',
            ]);
        }

        $this->assertResolvableCode($valueCode);

        if ($this->hasLiteralProgressThreshold($chartConfig)) {
            return;
        }

        $thresholdCode = $chartConfig['threshold_code'] ?? null;
        if (! is_string($thresholdCode) || $thresholdCode === '') {
            throw ValidationException::withMessages([
                'chart_config' => 'La configurazione di avanzamento richiede threshold_code oppure una soglia numerica.',
            ]);
        }

        $this->assertResolvableCode($thresholdCode);
    }

    /**
     * @param  array<string, mixed>  $chartConfig
     */
    private function hasLiteralProgressThreshold(array $chartConfig): bool
    {
        if (isset($chartConfig['threshold_amount']) && is_numeric($chartConfig['threshold_amount'])) {
            return true;
        }

        $parameters = $chartConfig['parameters'] ?? [];
        if (! is_array($parameters)) {
            return false;
        }

        foreach ($parameters as $parameter) {
            if (! is_array($parameter)) {
                continue;
            }

            if (($parameter['type'] ?? null) === 'number' && ($parameter['key'] ?? null) === 'threshold') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $chartConfig
     */
    private function validateTableConfig(array $chartConfig): void
    {
        $metricQuery = $chartConfig['metric_query'] ?? null;
        if (! is_array($metricQuery) || ! isset($metricQuery['datasource'], $metricQuery['measure'])) {
            throw ValidationException::withMessages([
                'chart_config' => 'Il widget tabella richiede una metric_query con sorgente e misura.',
            ]);
        }

        $table = $chartConfig['table'] ?? null;
        if (! is_array($table)) {
            throw ValidationException::withMessages([
                'chart_config' => 'Il widget tabella richiede la configurazione table (mode).',
            ]);
        }

        $mode = $table['mode'] ?? null;
        if (! in_array($mode, ['rows', 'aggregate'], true)) {
            throw ValidationException::withMessages([
                'chart_config' => 'La modalità tabella deve essere «rows» o «aggregate».',
            ]);
        }

        $datasource = (string) $metricQuery['datasource'];
        $meta = config("metric_queries.datasources.{$datasource}", []);

        $maxLimit = (int) config('metric_queries.max_row_limit', 50);
        if (isset($table['row_limit'])) {
            $limit = (int) $table['row_limit'];
            if ($limit < 1 || $limit > $maxLimit) {
                throw ValidationException::withMessages([
                    'chart_config' => "Il limite righe deve essere tra 1 e {$maxLimit}.",
                ]);
            }
        }

        if ($mode === 'aggregate') {
            $groupBy = $table['group_by'] ?? null;
            $allowedGroups = $meta['group_by_fields'] ?? [];
            if (! is_string($groupBy) || ! in_array($groupBy, $allowedGroups, true)) {
                throw ValidationException::withMessages([
                    'chart_config' => 'Seleziona un raggruppamento valido per la tabella aggregata.',
                ]);
            }
        }

        if (isset($table['columns']) && is_array($table['columns'])) {
            $allowedColumns = $meta['list_columns'] ?? [];
            foreach ($table['columns'] as $column) {
                if (! is_string($column) || ! in_array($column, $allowedColumns, true)) {
                    throw ValidationException::withMessages([
                        'chart_config' => "La colonna «{$column}» non è consentita.",
                    ]);
                }
            }
        }

        if (isset($table['sort']) && is_array($table['sort'])) {
            $sortField = $table['sort']['field'] ?? null;
            $allowedSort = $meta['sort_fields'] ?? [];
            if (! is_string($sortField) || ! in_array($sortField, $allowedSort, true)) {
                throw ValidationException::withMessages([
                    'chart_config' => 'Il campo di ordinamento non è valido.',
                ]);
            }
        }
    }

    private function assertResolvableCode(string $code): void
    {
        if (! $this->tokenParser->isValidCode($code)) {
            throw ValidationException::withMessages([
                'chart_config' => "Il codice variabile {$code} non è valido.",
            ]);
        }
    }
}
