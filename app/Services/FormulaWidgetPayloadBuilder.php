<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FormulaWidget;
use App\Models\User;
use App\Support\FormulaWidgetRuntimeContext;
use Carbon\Carbon;

class FormulaWidgetPayloadBuilder
{
    public function __construct(
        private readonly FormulaResolverService $formulaResolverService,
        private readonly FormulaPeriodResolver $formulaPeriodResolver,
        private readonly SystemVariableResolver $systemVariableResolver,
        private readonly AccountBalanceService $accountBalanceService,
        private readonly PortfolioSnapshotService $portfolioSnapshotService,
        private readonly FormulaWidgetParameterService $parameterService,
    ) {}

    /**
     * @param  array<string, string|int|null>  $runtimeOverrides
     * @return array<string, mixed>
     */
    public function build(FormulaWidget $widget, User $user, array $runtimeOverrides = []): array
    {
        $widget->loadMissing('financialVariable');
        $variable = $widget->financialVariable;

        $chartConfig = $widget->chart_config ?? [];
        $resolvedParameters = $this->parameterService->resolveValues($user, $chartConfig, $runtimeOverrides);
        $context = FormulaWidgetRuntimeContext::fromResolvedParameters($resolvedParameters);
        $period = $this->resolveWidgetPeriod($widget, $user, $context->periodOffset);

        $payload = match ($widget->display_type) {
            FormulaWidget::DISPLAY_KPI => $this->buildKpi($widget, $user, $variable->formula_string ?? (string) $variable->code, $period, $chartConfig, $context),
            FormulaWidget::DISPLAY_LINE, FormulaWidget::DISPLAY_AREA => $this->buildLineArea($widget, $user, $variable, $period, $chartConfig, $context),
            FormulaWidget::DISPLAY_BAR => $this->buildBar($widget, $user, $period, $chartConfig, $context),
            FormulaWidget::DISPLAY_HORIZONTAL_BAR => $this->buildHorizontalBar($widget, $user, $period, $chartConfig, $context),
            FormulaWidget::DISPLAY_PIE => $this->buildPie($widget, $user, $period, $chartConfig, $context),
            FormulaWidget::DISPLAY_TREEMAP => $this->buildTreemap($widget, $user, $period, $chartConfig, $context),
            FormulaWidget::DISPLAY_STACKED_BAR => $this->buildStackedBar($widget, $user, $period, $chartConfig, $context),
            FormulaWidget::DISPLAY_PROGRESS => $this->buildProgress($widget, $user, $period, $chartConfig, $context),
            default => throw new \InvalidArgumentException('Unsupported display type'),
        };

        return $this->attachParameters($user, $chartConfig, $resolvedParameters, $period, $payload);
    }

    /**
     * @param  array<int, array<string, string|int|null>>  $runtimeOverridesByWidgetId
     * @return array<string, array<string, mixed>>
     */
    public function buildMany(iterable $widgets, User $user, array $runtimeOverridesByWidgetId = []): array
    {
        $payloads = [];

        foreach ($widgets as $widget) {
            $overrides = $runtimeOverridesByWidgetId[$widget->id] ?? [];
            $payloads[(string) $widget->id] = $this->build($widget, $user, $overrides);
        }

        return $payloads;
    }

    public function buildForGuest(FormulaWidget $widget): array
    {
        $defaults = config('financial_variables.guest_preview_defaults', []);
        $chartConfig = $widget->chart_config ?? [];

        return match ($widget->display_type) {
            FormulaWidget::DISPLAY_KPI => [
                'type' => 'kpi',
                'name' => $widget->name,
                'value' => $defaults['period_net'] ?? 850.0,
                'periodLabel' => 'Anteprima demo',
                'delta' => 5.2,
                'format' => $chartConfig['format'] ?? 'currency',
                'parameters' => [],
            ],
            FormulaWidget::DISPLAY_PROGRESS => [
                'type' => 'progress',
                'name' => $widget->name,
                'value' => $defaults['period_income'] ?? 2800.0,
                'threshold' => $defaults['period_expenses'] ?? 1950.0,
                'percentage' => 49.4,
                'periodLabel' => 'Anteprima demo',
                'parameters' => [],
            ],
            default => [
                'type' => $widget->display_type,
                'name' => $widget->name,
                'points' => [
                    ['label' => 'Gen', 'value' => 1200.0],
                    ['label' => 'Feb', 'value' => 1500.0],
                    ['label' => 'Mar', 'value' => 1800.0],
                ],
                'series' => $chartConfig['series'] ?? [],
                'periodLabel' => 'Anteprima demo',
                'parameters' => [],
            ],
        };
    }

    /**
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    private function resolveWidgetPeriod(FormulaWidget $widget, User $user, int $periodOffset = 0): array
    {
        if ($widget->period_preset === null || $widget->period_preset === '') {
            $today = Carbon::now()->endOfDay();

            return [
                'start' => $today->copy()->startOfDay(),
                'end' => $today,
                'label' => 'Ad oggi',
            ];
        }

        if ($periodOffset !== 0) {
            return $this->formulaPeriodResolver->resolveWithOffset($widget->period_preset, $user, $periodOffset);
        }

        return $this->formulaPeriodResolver->resolve($widget->period_preset, $user);
    }

    /**
     * @param  array<string, mixed>  $chartConfig
     * @param  array<string, string>  $resolvedParameters
     * @param  array{start: Carbon, end: Carbon, label: string}  $period
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attachParameters(
        User $user,
        array $chartConfig,
        array $resolvedParameters,
        array $period,
        array $payload,
    ): array {
        $metadata = $this->parameterService->buildPayloadMetadata($user, $chartConfig, $resolvedParameters);

        if ($metadata === []) {
            return $payload;
        }

        foreach ($metadata as $index => $entry) {
            if (($entry['type'] ?? null) === FormulaWidgetParameterService::PERIOD_NAV_TYPE) {
                $metadata[$index]['display_label'] = $period['label'];
            }
        }

        $payload['parameters'] = $metadata;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $chartConfig
     * @return array<string, mixed>
     */
    private function buildKpi(
        FormulaWidget $widget,
        User $user,
        string $formula,
        array $period,
        array $chartConfig,
        FormulaWidgetRuntimeContext $context,
    ): array {
        if (($chartConfig['variant'] ?? '') === 'balance_summary') {
            return $this->buildBalanceSummaryKpi($widget, $user, $period);
        }

        $value = $this->formulaResolverService->evaluate(
            $user,
            $this->wrapAsFormula($formula),
            $period['start'],
            $period['end'],
            $context,
        );
        $delta = null;

        $deltaComparisonLabel = null;

        if ($chartConfig['show_delta'] ?? false) {
            $previous = $this->formulaPeriodResolver->previousPeriod($period['start'], $period['end']);
            $prevValue = $this->formulaResolverService->evaluate(
                $user,
                $this->wrapAsFormula($formula),
                $previous['start'],
                $previous['end'],
                $context,
            );
            $delta = $prevValue != 0.0
                ? round((($value - $prevValue) / abs($prevValue)) * 100, 1)
                : null;
            $deltaComparisonLabel = $chartConfig['delta_comparison_label']
                ?? $this->resolveDeltaComparisonLabel($widget->period_preset);
        }

        return [
            'type' => 'kpi',
            'name' => $widget->name,
            'value' => $value,
            'periodLabel' => $period['label'],
            'delta' => $delta,
            'deltaPolarity' => $chartConfig['delta_polarity'] ?? 'higher_is_better',
            'deltaComparisonLabel' => $deltaComparisonLabel,
            'format' => $chartConfig['format'] ?? 'currency',
        ];
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string}  $period
     * @return array<string, mixed>
     */
    private function buildBalanceSummaryKpi(FormulaWidget $widget, User $user, array $period): array
    {
        $householdId = $user->active_household_id;
        $accounts = Account::query()
            ->where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->get();

        $totalBalance = $this->accountBalanceService->computeHouseholdTotal($user, $accounts);
        $portfolioSnapshot = $this->portfolioSnapshotService->build($user);

        return [
            'type' => 'kpi',
            'variant' => 'balance_summary',
            'name' => $widget->name,
            'value' => round((float) $totalBalance, 2),
            'invested' => $portfolioSnapshot['investedValue'],
            'investedLinked' => $portfolioSnapshot['investedLinkedValue'],
            'patrimonioTotal' => $portfolioSnapshot['totalValue'],
            'accountsCount' => $accounts->count(),
            'periodLabel' => $period['label'],
            'delta' => null,
            'format' => 'currency',
        ];
    }

    /**
     * @param  array<string, mixed>  $chartConfig
     * @return array<string, mixed>
     */
    private function buildLineArea(
        FormulaWidget $widget,
        User $user,
        $variable,
        array $period,
        array $chartConfig,
        FormulaWidgetRuntimeContext $context,
    ): array {
        $formula = (string) $variable->formula_string;
        $code = $this->tokenParserExtractSingleCode($formula);
        $points = $code !== null
            ? $this->formulaResolverService->evaluateMonthlySeries($user, $code, $period['start'], $period['end'], null, $context)
            : $this->formulaResolverService->evaluateMonthlySeries($user, $formula, $period['start'], $period['end'], null, $context);

        return [
            'type' => $widget->display_type === FormulaWidget::DISPLAY_AREA ? 'area' : 'line',
            'name' => $widget->name,
            'variant' => $widget->display_type,
            'points' => $points,
            'series' => $chartConfig['series'] ?? [],
            'periodLabel' => $period['label'],
        ];
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string}  $period
     * @param  array<string, mixed>  $chartConfig
     * @return array<int, array{label: string, value: float, color: string|null, percentage?: float}>
     */
    private function buildCategorySeries(
        User $user,
        array $period,
        array $chartConfig,
        FormulaWidgetRuntimeContext $context,
    ): array {
        $series = $chartConfig['series'] ?? [];
        $codes = array_column($series, 'code');
        $values = $this->formulaResolverService->evaluateCodesForPeriod(
            $user,
            $codes,
            $period['start'],
            $period['end'],
            $context,
        );

        $categories = [];
        foreach ($series as $entry) {
            $code = $entry['code'];
            $categories[] = [
                'label' => $entry['label'] ?? $code,
                'value' => abs((float) ($values[$code] ?? 0.0)),
                'color' => $entry['color'] ?? null,
            ];
        }

        $total = array_sum(array_column($categories, 'value'));
        if ($total > 0) {
            foreach ($categories as $index => $category) {
                $categories[$index]['percentage'] = round(($category['value'] / $total) * 100, 1);
            }
        }

        return $categories;
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string}  $period
     * @param  array<string, mixed>  $chartConfig
     * @return array<string, mixed>
     */
    private function buildBar(
        FormulaWidget $widget,
        User $user,
        array $period,
        array $chartConfig,
        FormulaWidgetRuntimeContext $context,
    ): array {
        return [
            'type' => 'bar',
            'name' => $widget->name,
            'categories' => $this->buildCategorySeries($user, $period, $chartConfig, $context),
            'periodLabel' => $period['label'],
        ];
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string}  $period
     * @param  array<string, mixed>  $chartConfig
     * @return array<string, mixed>
     */
    private function buildHorizontalBar(
        FormulaWidget $widget,
        User $user,
        array $period,
        array $chartConfig,
        FormulaWidgetRuntimeContext $context,
    ): array {
        return [
            'type' => 'horizontal_bar',
            'name' => $widget->name,
            'categories' => $this->buildCategorySeries($user, $period, $chartConfig, $context),
            'periodLabel' => $period['label'],
        ];
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string}  $period
     * @param  array<string, mixed>  $chartConfig
     * @return array<string, mixed>
     */
    private function buildPie(
        FormulaWidget $widget,
        User $user,
        array $period,
        array $chartConfig,
        FormulaWidgetRuntimeContext $context,
    ): array {
        return [
            'type' => 'pie',
            'name' => $widget->name,
            'categories' => $this->buildCategorySeries($user, $period, $chartConfig, $context),
            'periodLabel' => $period['label'],
        ];
    }

    /**
     * @param  array{start: Carbon, end: Carbon, label: string}  $period
     * @param  array<string, mixed>  $chartConfig
     * @return array<string, mixed>
     */
    private function buildTreemap(
        FormulaWidget $widget,
        User $user,
        array $period,
        array $chartConfig,
        FormulaWidgetRuntimeContext $context,
    ): array {
        return [
            'type' => 'treemap',
            'name' => $widget->name,
            'categories' => $this->buildCategorySeries($user, $period, $chartConfig, $context),
            'periodLabel' => $period['label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $chartConfig
     * @return array<string, mixed>
     */
    private function buildStackedBar(
        FormulaWidget $widget,
        User $user,
        array $period,
        array $chartConfig,
        FormulaWidgetRuntimeContext $context,
    ): array {
        $series = $chartConfig['series'] ?? [];
        $buckets = $this->formulaPeriodResolver->monthBuckets($period['start'], $period['end']);
        $points = [];

        foreach ($buckets as $bucket) {
            $values = $this->formulaResolverService->evaluateCodesForPeriod(
                $user,
                array_column($series, 'code'),
                $bucket['start'],
                $bucket['end'],
                $context,
            );

            $points[] = [
                'label' => $bucket['label'],
                'series' => $values,
            ];
        }

        return [
            'type' => 'stacked_bar',
            'name' => $widget->name,
            'points' => $points,
            'series' => $series,
            'periodLabel' => $period['label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $chartConfig
     * @return array<string, mixed>
     */
    private function buildProgress(
        FormulaWidget $widget,
        User $user,
        array $period,
        array $chartConfig,
        FormulaWidgetRuntimeContext $context,
    ): array {
        $valueCode = $chartConfig['value_code'] ?? 'period_income';
        $thresholdCode = $chartConfig['threshold_code'] ?? 'period_expenses';

        $value = $this->formulaResolverService->resolveCode($user, $valueCode, $period['start'], $period['end'], 0, [], $context);
        $threshold = $this->formulaResolverService->resolveCode($user, $thresholdCode, $period['start'], $period['end'], 0, [], $context);
        $percentage = $threshold > 0 ? round(($value / $threshold) * 100, 1) : 0.0;

        return [
            'type' => 'progress',
            'name' => $widget->name,
            'value' => $value,
            'threshold' => $threshold,
            'percentage' => $percentage,
            'periodLabel' => $period['label'],
        ];
    }

    private function resolveDeltaComparisonLabel(?string $periodPreset): string
    {
        if ($periodPreset === null || $periodPreset === '') {
            return 'periodo precedente';
        }

        $presets = config('financial_variables.period_presets', []);

        return $presets[$periodPreset]['previous_period_label'] ?? 'periodo precedente';
    }

    private function wrapAsFormula(string $value): string
    {
        if (str_contains($value, '[')) {
            return $value;
        }

        return "[{$value}]";
    }

    private function tokenParserExtractSingleCode(string $formula): ?string
    {
        if (preg_match('/^\[(?<code>[a-z][a-z0-9_]*)\]$/', trim($formula), $matches)) {
            return $matches['code'];
        }

        return null;
    }
}
