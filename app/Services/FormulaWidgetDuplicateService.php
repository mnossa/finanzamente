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
            ], fn ($value) => $value !== null),
            FormulaWidget::DISPLAY_PROGRESS => [
                'value_code' => (string) ($chartConfig['value_code'] ?? ''),
                'threshold_code' => (string) ($chartConfig['threshold_code'] ?? ''),
            ],
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
            default => [],
        };
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
}
