<?php

namespace App\Services;

use App\Jobs\PurgeSoftDeletedFormulaWidgetJob;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FormulaWidgetRemovalService
{
    public const UNDO_SECONDS = 30;

    public function __construct(
        private readonly FormulaWidgetDashboardPinService $dashboardPinService,
    ) {}

    /**
     * Soft-delete con finestra di annullamento, poi purge reale via job.
     *
     * @return array{widget_id: int, expires_at: string}
     */
    public function remove(User $user, FormulaWidget $widget): array
    {
        if ((int) $widget->user_id !== (int) $user->id) {
            abort(403);
        }

        if ($widget->isOfficialProtected()) {
            abort(403, 'I widget ufficiali non possono essere eliminati.');
        }

        return DB::transaction(function () use ($user, $widget) {
            $pins = $this->dashboardPinService->snapshotPins($user, $widget);
            $this->dashboardPinService->removeFromAllLayouts($user, $widget);

            $widget->delete();

            Cache::put(
                self::undoCacheKey($widget->id),
                [
                    'user_id' => $user->id,
                    'pins' => $pins,
                ],
                now()->addSeconds(self::UNDO_SECONDS + 5),
            );

            PurgeSoftDeletedFormulaWidgetJob::dispatch($widget->id)
                ->delay(now()->addSeconds(self::UNDO_SECONDS));

            return [
                'widget_id' => $widget->id,
                'expires_at' => now()->addSeconds(self::UNDO_SECONDS)->toIso8601String(),
            ];
        });
    }

    public function restore(User $user, FormulaWidget $widget): void
    {
        if ((int) $widget->user_id !== (int) $user->id) {
            abort(403);
        }

        if (! $widget->trashed()) {
            abort(404);
        }

        if ($widget->isOfficialProtected()) {
            abort(403);
        }

        $cache = Cache::pull(self::undoCacheKey($widget->id));

        DB::transaction(function () use ($user, $widget, $cache) {
            $widget->restore();

            $pins = is_array($cache['pins'] ?? null) ? $cache['pins'] : [];
            $this->dashboardPinService->restorePins($user, $widget, $pins);
        });
    }

    /**
     * Eliminazione definitiva dopo la finestra di undo.
     * I clone di altri utenti restano: si stacca solo source_id.
     */
    public function purge(FormulaWidget $widget): void
    {
        if ($widget->is_official_template) {
            abort(403, 'I widget ufficiali non possono essere eliminati.');
        }

        if (! $widget->trashed()) {
            return;
        }

        DB::transaction(function () use ($widget) {
            FormulaWidget::withTrashed()
                ->where('source_id', $widget->id)
                ->update(['source_id' => null]);

            $user = $widget->user;
            if ($user !== null) {
                $this->dashboardPinService->removeFromAllLayouts($user, $widget);
            }

            $variableId = $widget->financial_variable_id;
            $widget->forceDelete();

            Cache::forget(self::undoCacheKey($widget->id));

            if ($variableId === null) {
                return;
            }

            $stillUsed = FormulaWidget::withTrashed()
                ->where('financial_variable_id', $variableId)
                ->exists();

            if ($stillUsed) {
                return;
            }

            FinancialVariable::query()
                ->where('id', $variableId)
                ->where('user_id', $widget->user_id)
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
                    if ($user === null) {
                        return;
                    }

                    $this->dashboardPinService->removeFromAllLayouts($user, $clone);
                    FormulaWidget::withTrashed()
                        ->where('source_id', $clone->id)
                        ->update(['source_id' => null]);
                    $variableId = $clone->financial_variable_id;
                    $clone->forceDelete();

                    if ($variableId === null) {
                        return;
                    }

                    $stillUsed = FormulaWidget::withTrashed()
                        ->where('financial_variable_id', $variableId)
                        ->exists();

                    if ($stillUsed) {
                        return;
                    }

                    FinancialVariable::query()
                        ->where('id', $variableId)
                        ->where('user_id', $clone->user_id)
                        ->where('is_official_template', false)
                        ->delete();
                });

            $variableId = $official->financial_variable_id;
            $official->forceDelete();

            if ($variableId !== null) {
                FinancialVariable::query()
                    ->where('id', $variableId)
                    ->where('is_official_template', true)
                    ->delete();
            }
        }
    }

    public static function undoCacheKey(int $widgetId): string
    {
        return "formula_widget_undo:{$widgetId}";
    }
}
