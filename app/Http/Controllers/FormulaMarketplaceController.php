<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewMarketplaceWidgetRequest;
use App\Models\FormulaWidget;
use App\Services\FinancialVariableCloneService;
use App\Services\FormulaWidgetDashboardPinService;
use App\Services\FormulaWidgetPreviewService;
use App\Services\FormulaWidgetRemovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FormulaMarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $retiredSlugs = config('financial_variables.retired_official_template_slugs', []);

        $official = FormulaWidget::query()
            ->where('is_official_template', true)
            ->where('is_public', true)
            ->when($retiredSlugs !== [], fn ($q) => $q->whereNotIn('template_slug', $retiredSlugs))
            ->with('financialVariable:id,code,name,type,formula_string')
            ->orderBy('name')
            ->get()
            ->map(fn (FormulaWidget $widget) => $this->formatListing($widget, $user?->id));

        $community = FormulaWidget::query()
            ->where('is_public', true)
            ->where('is_official_template', false)
            ->with('financialVariable:id,code,name,type,formula_string')
            ->orderByDesc('downloads_count')
            ->limit(50)
            ->get()
            ->map(fn (FormulaWidget $widget) => $this->formatListing($widget, $user?->id));

        return Inertia::render('FormulaWidgets/Marketplace', [
            'officialTemplates' => $official,
            'communityWidgets' => $community,
        ]);
    }

    public function preview(PreviewMarketplaceWidgetRequest $request, FormulaWidgetPreviewService $previewService): JsonResponse
    {
        try {
            return response()->json(
                $previewService->buildFromMarketplace($request->user(), $request->validated()),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => collect($e->errors())->flatten()->values()->all(),
            ], 422);
        }
    }

    public function installTemplate(Request $request, string $templateSlug, FinancialVariableCloneService $cloneService): RedirectResponse
    {
        $user = Auth::user();
        $widget = $cloneService->installTemplate($user, $templateSlug);

        if ($request->boolean('pin')) {
            app(FormulaWidgetDashboardPinService::class)->pin($user, $widget);

            return redirect()
                ->route('dashboard')
                ->with('success', 'Widget aggiunto alla dashboard.');
        }

        return redirect()
            ->route('formula-widgets.index')
            ->with('success', 'Template installato nella tua libreria.')
            ->with('installedWidgetId', $widget->id);
    }

    public function installWidget(FormulaWidget $formulaWidget, FinancialVariableCloneService $cloneService): RedirectResponse
    {
        $user = Auth::user();

        if (! $formulaWidget->is_public) {
            abort(404);
        }

        $cloned = $cloneService->installWidget($user, $formulaWidget);

        return redirect()
            ->route('formula-widgets.index')
            ->with('success', 'Widget installato nella tua libreria.')
            ->with('installedWidgetId', $cloned->id);
    }

    public function uninstallTemplate(string $templateSlug, FormulaWidgetRemovalService $removalService): RedirectResponse
    {
        $user = Auth::user();

        $official = FormulaWidget::query()
            ->where('template_slug', $templateSlug)
            ->where('is_official_template', true)
            ->firstOrFail();

        $installed = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->where('source_id', $official->id)
            ->firstOrFail();

        $removalService->remove($user, $installed);

        return redirect()
            ->route('formula-marketplace.index')
            ->with('success', 'Template rimosso dalla tua libreria.');
    }

    public function uninstallWidget(FormulaWidget $formulaWidget, FormulaWidgetRemovalService $removalService): RedirectResponse
    {
        $user = Auth::user();

        if (! $formulaWidget->is_public) {
            abort(404);
        }

        $installed = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->where('source_id', $formulaWidget->id)
            ->firstOrFail();

        $removalService->remove($user, $installed);

        return redirect()
            ->route('formula-marketplace.index')
            ->with('success', 'Widget rimosso dalla tua libreria.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatListing(FormulaWidget $widget, ?int $userId): array
    {
        $installedWidget = null;
        if ($userId !== null) {
            $installedWidget = FormulaWidget::query()
                ->where('user_id', $userId)
                ->where('source_id', $widget->id)
                ->first();
        }

        return [
            ...FormulaWidgetController::formatWidget($widget),
            'template_slug' => $widget->template_slug,
            'is_official_template' => $widget->is_official_template,
            'installed' => $installedWidget !== null,
            'installed_widget_id' => $installedWidget?->id,
        ];
    }
}
