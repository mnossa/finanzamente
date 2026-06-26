<?php

namespace App\Services;

use App\Models\FormulaWidget;
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
        ], true) || ($chartConfig['show_delta'] ?? false);

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
            default => null,
        };

        $this->parameterService->validateChartConfig($chartConfig, $isPublic);

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
        foreach (['value_code', 'threshold_code'] as $key) {
            $code = $chartConfig[$key] ?? null;
            if (! is_string($code) || $code === '') {
                throw ValidationException::withMessages([
                    'chart_config' => 'La configurazione di avanzamento richiede value_code e threshold_code.',
                ]);
            }

            $this->assertResolvableCode($code);
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
