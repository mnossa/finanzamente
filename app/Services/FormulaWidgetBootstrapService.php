<?php

namespace App\Services;

use App\Models\DashboardLayout;
use App\Models\FormulaWidget;
use App\Models\User;

class FormulaWidgetBootstrapService
{
    public function __construct(
        private readonly FinancialVariableCloneService $cloneService,
    ) {}

    public function provisionForUser(User $user): void
    {
        if ($user->formulaWidgets()->exists()) {
            return;
        }

        $slugs = config('financial_variables.bootstrap_template_slugs', []);
        $clonedWidgets = [];

        foreach ($slugs as $slug) {
            if (! FormulaWidget::query()->where('template_slug', $slug)->where('is_official_template', true)->exists()) {
                continue;
            }

            $clonedWidgets[] = $this->cloneService->installTemplate($user, $slug);
        }

        if ($clonedWidgets === []) {
            return;
        }

        $this->ensureDashboardLayout($user, $clonedWidgets);
    }

    /**
     * @param  array<int, FormulaWidget>  $widgets
     */
    private function ensureDashboardLayout(User $user, array $widgets): void
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return;
        }

        $layout = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $householdId)
            ->first();

        $config = $layout?->config ?? DashboardLayout::defaultConfig();
        $existingIds = array_column($config['widgets'] ?? [], 'id');

        $position = 0;
        $newEntries = [];

        foreach ($widgets as $widget) {
            $widgetId = "formula_widget_{$widget->id}";
            if (in_array($widgetId, $existingIds, true)) {
                continue;
            }

            $newEntries[] = [
                'id' => $widgetId,
                'visible' => true,
                'position' => $position,
                'size' => $widget->default_size,
            ];
            $position++;
        }

        if ($newEntries === []) {
            return;
        }

        $legacyWidgets = array_values(array_filter(
            $config['widgets'] ?? [],
            fn (array $entry) => ! $this->isTierALegacyWidget($entry['id'] ?? ''),
        ));

        foreach ($legacyWidgets as $index => $entry) {
            $legacyWidgets[$index]['position'] = $position;
            $position++;
        }

        $config['widgets'] = array_merge($newEntries, $legacyWidgets);

        DashboardLayout::updateOrCreate(
            [
                'user_id' => $user->id,
                'household_id' => $householdId,
            ],
            ['config' => $config],
        );
    }

    private function isTierALegacyWidget(string $widgetId): bool
    {
        return in_array($widgetId, DashboardLayout::TIER_A_LEGACY_WIDGET_IDS, true)
            || array_key_exists($widgetId, config('financial_variables.legacy_widget_replacements', []));
    }
}
