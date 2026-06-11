<?php

namespace App\Services;

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FormulaWidgetRemovalService
{
    public function __construct(
        private readonly FormulaWidgetDashboardPinService $dashboardPinService,
    ) {}

    public function remove(User $user, FormulaWidget $widget): void
    {
        if ((int) $widget->user_id !== (int) $user->id) {
            abort(403);
        }

        if ($widget->is_official_template) {
            abort(403);
        }

        DB::transaction(function () use ($user, $widget) {
            $this->dashboardPinService->removeFromLayout($user, $widget);

            $variableId = $widget->financial_variable_id;
            $widget->delete();

            if ($variableId === null) {
                return;
            }

            $stillUsed = FormulaWidget::query()
                ->where('financial_variable_id', $variableId)
                ->exists();

            if ($stillUsed) {
                return;
            }

            FinancialVariable::query()
                ->where('id', $variableId)
                ->where('user_id', $user->id)
                ->where('is_official_template', false)
                ->delete();
        });
    }

    /**
     * @param  list<string>  $templateSlugs
     */
    public function purgeRetiredOfficialTemplates(array $templateSlugs): void
    {
        if ($templateSlugs === []) {
            return;
        }

        $officialWidgets = FormulaWidget::query()
            ->where('is_official_template', true)
            ->whereIn('template_slug', $templateSlugs)
            ->get();

        foreach ($officialWidgets as $official) {
            FormulaWidget::query()
                ->where('source_id', $official->id)
                ->with('user')
                ->each(function (FormulaWidget $clone) {
                    $user = $clone->user;
                    if ($user !== null) {
                        $this->remove($user, $clone);
                    }
                });

            $variableId = $official->financial_variable_id;
            $official->delete();

            if ($variableId !== null) {
                FinancialVariable::query()
                    ->where('id', $variableId)
                    ->where('is_official_template', true)
                    ->delete();
            }
        }
    }
}
