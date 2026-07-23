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

        $this->ensureHomeLayout($user);
    }

    private function ensureHomeLayout(User $user): void
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return;
        }

        if (DashboardLayout::findHome($user->id, $householdId) !== null) {
            return;
        }

        DashboardLayout::create([
            'user_id' => $user->id,
            'household_id' => $householdId,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfigForUser($user),
        ]);
    }
}
