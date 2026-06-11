<?php

namespace App\Services;

use App\Models\DashboardLayout;
use App\Models\FormulaWidget;
use App\Models\User;

class FormulaWidgetDashboardPinService
{
    public function pin(User $user, FormulaWidget $widget): void
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return;
        }

        $widgetId = "formula_widget_{$widget->id}";
        $layout = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $householdId)
            ->first();

        $config = $layout?->config ?? DashboardLayout::defaultConfig();
        $widgets = $config['widgets'] ?? [];

        foreach ($widgets as $entry) {
            if (($entry['id'] ?? '') === $widgetId) {
                return;
            }
        }

        $maxPosition = collect($widgets)->max('position') ?? -1;

        $widgets[] = [
            'id' => $widgetId,
            'visible' => true,
            'position' => $maxPosition + 1,
            'size' => $widget->default_size ?? 'md',
        ];

        DashboardLayout::updateOrCreate(
            [
                'user_id' => $user->id,
                'household_id' => $householdId,
            ],
            ['config' => ['widgets' => $widgets]],
        );
    }

    public function removeFromLayout(User $user, FormulaWidget $widget): void
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return;
        }

        $widgetLayoutId = "formula_widget_{$widget->id}";
        $layout = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $householdId)
            ->first();

        if ($layout === null) {
            return;
        }

        $widgets = array_values(array_filter(
            $layout->config['widgets'] ?? [],
            fn (array $entry) => ($entry['id'] ?? '') !== $widgetLayoutId,
        ));

        foreach ($widgets as $index => $entry) {
            $widgets[$index]['position'] = $index;
        }

        $layout->update(['config' => ['widgets' => $widgets]]);
    }
}
