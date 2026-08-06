<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewMarketplaceWidgetRequest;
use App\Models\FormulaWidget;
use App\Models\User;
use App\Services\FinancialVariableCloneService;
use App\Services\FormulaWidgetDashboardPinService;
use App\Services\FormulaWidgetDuplicateService;
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
    public function __construct(
        private readonly FormulaWidgetDuplicateService $formulaWidgetDuplicateService,
    ) {}

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
            'chartTypes' => config('financial_variables.chart_types', []),
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
        $sourceWidget = FormulaWidget::query()
            ->where('template_slug', $templateSlug)
            ->where('is_official_template', true)
            ->with('financialVariable')
            ->firstOrFail();

        $duplicate = $this->assertNoDuplicateOrRedirect($user, $sourceWidget);
        if ($duplicate instanceof RedirectResponse) {
            return $duplicate;
        }

        $widget = $cloneService->installTemplate($user, $templateSlug);

        if ($request->boolean('pin')) {
            $pinService = app(FormulaWidgetDashboardPinService::class);
            $boardId = $request->filled('board_id') ? $request->integer('board_id') : null;
            $pinResult = $pinService->pin($user, $widget, $boardId);

            if ($pinResult === FormulaWidgetDashboardPinService::RESULT_NEEDS_BOARD_CHOICE) {
                return redirect()
                    ->route('formula-widgets.pin.choose', $widget)
                    ->with('success', 'Template installato. Scegli in quale dashboard aggiungerlo.');
            }

            if ($pinResult === FormulaWidgetDashboardPinService::RESULT_NO_CUSTOM_BOARD) {
                return redirect()
                    ->route('dashboard.boards.index')
                    ->with('success', 'Template installato. Crea una dashboard per aggiungerlo.')
                    ->with('installedWidgetId', $widget->id);
            }

            $target = $pinService->resolvePinnedBoard($user, $widget, $boardId);

            return redirect()
                ->route('dashboard', $target && ! $target->is_home ? ['board' => $target->id] : [])
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

        $formulaWidget->loadMissing('financialVariable');

        $duplicate = $this->assertNoDuplicateOrRedirect($user, $formulaWidget);
        if ($duplicate instanceof RedirectResponse) {
            return $duplicate;
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

        $undo = $removalService->remove($user, $installed);

        return redirect()
            ->route('formula-marketplace.index')
            ->with('success', 'Template rimosso dalla tua libreria. Puoi annullare entro 30 secondi.')
            ->with('undoFormulaWidget', $undo);
    }

    public function uninstallWidget(FormulaWidget $formulaWidget, FormulaWidgetRemovalService $removalService): RedirectResponse
    {
        $user = Auth::user();

        if (! $formulaWidget->is_public) {
            abort(404);
        }

        if ($formulaWidget->is_official_template) {
            abort(404);
        }

        $installed = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->where('source_id', $formulaWidget->id)
            ->firstOrFail();

        $undo = $removalService->remove($user, $installed);

        return redirect()
            ->route('formula-marketplace.index')
            ->with('success', 'Widget rimosso. Puoi annullare entro 30 secondi.')
            ->with('undoFormulaWidget', $undo);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatMarketplaceSuggestion(FormulaWidget $widget, ?int $userId = null): array
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
            'description' => (string) (config("financial_variables.chart_types.{$widget->display_type}.description") ?? ''),
            'template_slug' => $widget->template_slug,
            'is_official_template' => $widget->is_official_template,
            'installed' => $installedWidget !== null,
            'installed_widget_id' => $installedWidget?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatListing(FormulaWidget $widget, ?int $userId): array
    {
        return self::formatMarketplaceSuggestion($widget, $userId);
    }

    private function assertNoDuplicateOrRedirect(User $user, FormulaWidget $sourceWidget): ?RedirectResponse
    {
        $sourceWidget->loadMissing('financialVariable');

        if ($sourceWidget->financialVariable === null) {
            return null;
        }

        $duplicate = $this->formulaWidgetDuplicateService->findDuplicate(
            $user,
            $sourceWidget->financialVariable,
            $sourceWidget->display_type,
            $sourceWidget->period_preset,
            $sourceWidget->chart_config,
        );

        if ($duplicate === null) {
            return null;
        }

        $duplicate->loadMissing('financialVariable:id,code,name,type,formula_string');

        return redirect()
            ->back()
            ->withErrors([
                'widget' => 'Già pronto: hai un widget equivalente in libreria. Usalo invece di installarne un altro dalla galleria.',
            ])
            ->with('duplicateWidget', FormulaWidgetController::formatWidget($duplicate));
    }
}
