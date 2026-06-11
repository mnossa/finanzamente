<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewFormulaWidgetRequest;
use App\Http\Requests\StoreFormulaWidgetRequest;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Services\FormulaResolverService;
use App\Services\FormulaWidgetDashboardPinService;
use App\Services\FormulaWidgetPreviewService;
use App\Services\FormulaWidgetRemovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FormulaWidgetController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FormulaWidget::class);

        $user = $request->user();

        $widgets = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->where('is_official_template', false)
            ->with('financialVariable:id,code,name,type')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (FormulaWidget $widget) => $this->formatWidget($widget));

        return Inertia::render('FormulaWidgets/Index', [
            'widgets' => $widgets,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', FormulaWidget::class);

        $user = $request->user();
        $available = app(FormulaResolverService::class)->listAvailableVariables($user);

        $variables = FinancialVariable::query()
            ->where('user_id', $user->id)
            ->where('is_official_template', false)
            ->orderBy('name')
            ->get()
            ->map(fn (FinancialVariable $v) => FinancialVariableController::formatVariable($v));

        return Inertia::render('FormulaWidgets/Create', [
            'variables' => $variables,
            'systemVariables' => $available['system'],
            'chartTypes' => config('financial_variables.chart_types', []),
            'periodPresets' => config('financial_variables.period_presets', []),
        ]);
    }

    public function preview(PreviewFormulaWidgetRequest $request, FormulaWidgetPreviewService $previewService): JsonResponse
    {
        $this->authorize('create', FormulaWidget::class);

        try {
            return response()->json(
                $previewService->build($request->user(), $request->validated()),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => collect($e->errors())->flatten()->values()->all(),
            ], 422);
        }
    }

    public function store(StoreFormulaWidgetRequest $request, FormulaWidgetDashboardPinService $pinService): RedirectResponse
    {
        $this->authorize('create', FormulaWidget::class);

        $user = Auth::user();
        $data = $request->validated();

        $widget = FormulaWidget::create([
            'user_id' => $user->id,
            'financial_variable_id' => $data['financial_variable_id'],
            'name' => $data['name'],
            'display_type' => $data['display_type'],
            'period_preset' => $data['period_preset'] ?? null,
            'chart_config' => $data['chart_config'] ?? null,
            'default_size' => $data['default_size'] ?? 'md',
            'is_public' => (bool) ($data['is_public'] ?? false),
            'share_token' => ($data['is_public'] ?? false) ? $this->generateShareToken() : null,
        ]);

        if ($request->boolean('pin_to_dashboard')) {
            $pinService->pin($user, $widget);
        }

        return redirect()
            ->route('formula-widgets.index')
            ->with('success', 'Widget creato con successo.');
    }

    public function destroy(FormulaWidget $formulaWidget, FormulaWidgetRemovalService $removalService): RedirectResponse
    {
        $this->authorize('delete', $formulaWidget);

        $removalService->remove(Auth::user(), $formulaWidget);

        return redirect()
            ->route('formula-widgets.index')
            ->with('success', 'Widget rimosso.');
    }

    public function pin(FormulaWidget $formulaWidget, FormulaWidgetDashboardPinService $pinService): RedirectResponse
    {
        $this->authorize('view', $formulaWidget);

        $user = Auth::user();

        if ((int) $formulaWidget->user_id !== (int) $user->id) {
            abort(403);
        }

        $pinService->pin($user, $formulaWidget);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Widget aggiunto alla dashboard.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatWidget(FormulaWidget $widget): array
    {
        return [
            'id' => $widget->id,
            'name' => $widget->name,
            'display_type' => $widget->display_type,
            'period_preset' => $widget->period_preset,
            'chart_config' => $widget->chart_config,
            'default_size' => $widget->default_size,
            'is_public' => $widget->is_public,
            'share_token' => $widget->share_token,
            'downloads_count' => $widget->downloads_count,
            'financial_variable' => $widget->relationLoaded('financialVariable') && $widget->financialVariable
                ? FinancialVariableController::formatVariable($widget->financialVariable)
                : null,
        ];
    }

    private function generateShareToken(): string
    {
        return 'w_'.Str::lower(Str::random(10));
    }
}
