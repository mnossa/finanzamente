<?php

use App\Models\DashboardLayout;
use App\Models\FormulaWidget;
use App\Models\User;
use App\Services\FinancialVariableCloneService;
use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * One-shot: ripristina template ufficiale Saldo conti e ripinna Home Essenziale (D3).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new FormulaWidgetTemplateSeeder)->run();

        $homeEssentialSlugs = array_keys(
            config('financial_variables.home_essential_formula_widgets', []),
        );

        if ($homeEssentialSlugs === []) {
            $homeEssentialSlugs = config('financial_variables.home_essential_formula_slugs', []);
        }

        $cloneService = app(FinancialVariableCloneService::class);

        User::query()
            ->whereNotNull('active_household_id')
            ->orderBy('id')
            ->each(function (User $user) use ($cloneService, $homeEssentialSlugs): void {
                foreach ($homeEssentialSlugs as $slug) {
                    if (! is_string($slug) || $slug === '') {
                        continue;
                    }

                    $official = FormulaWidget::query()
                        ->where('template_slug', $slug)
                        ->where('is_official_template', true)
                        ->first();

                    if ($official === null) {
                        continue;
                    }

                    $hasClone = FormulaWidget::query()
                        ->where('user_id', $user->id)
                        ->where('source_id', $official->id)
                        ->exists();

                    if (! $hasClone) {
                        $cloneService->installTemplate($user, $slug);
                    }
                }

                $home = DashboardLayout::findHome($user->id, $user->active_household_id);

                if ($home === null) {
                    return;
                }

                $home->update([
                    'config' => DashboardLayout::essentialConfigForUser($user),
                ]);
            });
    }

    public function down(): void
    {
        // Irreversibile.
    }
};
