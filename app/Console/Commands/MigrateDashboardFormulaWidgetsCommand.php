<?php

namespace App\Console\Commands;

use App\Models\DashboardLayout;
use App\Models\FormulaWidget;
use App\Models\User;
use App\Services\FinancialVariableCloneService;
use App\Services\FormulaWidgetBootstrapService;
use App\Services\FormulaWidgetLayoutNormalizer;
use Illuminate\Console\Command;

class MigrateDashboardFormulaWidgetsCommand extends Command
{
    protected $signature = 'formula-widgets:migrate-dashboard-layouts {--user= : Migrate a single user id}';

    protected $description = 'Replace Tier A legacy dashboard widgets with installed formula widget templates';

    public function handle(
        FormulaWidgetBootstrapService $bootstrapService,
        FinancialVariableCloneService $cloneService,
        FormulaWidgetLayoutNormalizer $layoutNormalizer,
    ): int {
        $userQuery = User::query();

        if ($userId = $this->option('user')) {
            $userQuery->where('id', $userId);
        }

        $users = $userQuery->get();
        $replacements = config('financial_variables.legacy_widget_replacements', []);

        foreach ($users as $user) {
            if ($user->active_household_id === null) {
                continue;
            }

            if (! $user->formulaWidgets()->exists()) {
                $bootstrapService->provisionForUser($user);
            }

            $layout = DashboardLayout::query()
                ->where('user_id', $user->id)
                ->where('household_id', $user->active_household_id)
                ->first();

            if ($layout === null) {
                continue;
            }

            $config = $layout->config ?? DashboardLayout::defaultConfig();
            $widgets = $config['widgets'] ?? [];
            $installedBySlug = FormulaWidget::query()
                ->where('user_id', $user->id)
                ->whereNotNull('source_id')
                ->with('source')
                ->get()
                ->filter(fn (FormulaWidget $widget) => $widget->source?->template_slug !== null)
                ->keyBy(fn (FormulaWidget $widget) => (string) $widget->source?->template_slug);

            $position = 0;
            $newWidgets = [];

            foreach ($widgets as $entry) {
                $legacyId = $entry['id'] ?? '';

                if (! array_key_exists($legacyId, $replacements)) {
                    $entry['position'] = $position++;
                    $newWidgets[] = $entry;

                    continue;
                }

                foreach ($replacements[$legacyId] as $slug) {
                    $widget = $installedBySlug->get($slug);

                    if ($widget === null && FormulaWidget::query()->where('template_slug', $slug)->where('is_official_template', true)->exists()) {
                        $widget = $cloneService->installTemplate($user, $slug);
                        $installedBySlug->put($slug, $widget);
                    }

                    if ($widget === null) {
                        continue;
                    }

                    $newWidgets[] = [
                        'id' => "formula_widget_{$widget->id}",
                        'visible' => $entry['visible'] ?? true,
                        'position' => $position++,
                        'size' => $entry['size'] ?? $widget->default_size,
                    ];
                }
            }

            $config['widgets'] = $newWidgets;
            $layoutNormalizer->syncTemplateConfigs($user);
            $layout->update(['config' => $layoutNormalizer->normalize($user, $config)]);
        }

        $this->info('Dashboard layouts migrated to formula widgets.');

        return self::SUCCESS;
    }
}
