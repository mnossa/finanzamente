<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewFormulaWidgetRequest;
use App\Http\Requests\StoreFormulaWidgetRequest;
use App\Http\Requests\UpdateFormulaWidgetRequest;
use App\Models\Account;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use App\Services\FormulaResolverService;
use App\Services\FormulaWidgetDashboardPinService;
use App\Services\FormulaWidgetDuplicateService;
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
    public function __construct(
        private readonly FormulaWidgetDuplicateService $formulaWidgetDuplicateService,
    ) {}

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

        $accounts = $this->accountOptionsForUser($user);

        return Inertia::render('FormulaWidgets/Create', [
            'variables' => $variables,
            'systemVariables' => $available['system'],
            'chartTypes' => config('financial_variables.chart_types', []),
            'periodPresets' => config('financial_variables.period_presets', []),
            'accounts' => $accounts,
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

        $duplicate = $this->formulaWidgetDuplicateService->findDuplicateByVariableId(
            $user,
            (int) $data['financial_variable_id'],
            $data['display_type'],
            $data['period_preset'] ?? null,
            $data['chart_config'] ?? null,
        );

        if ($duplicate !== null) {
            $duplicate->loadMissing('financialVariable:id,code,name,type,formula_string');

            return $this->redirectOwnDuplicate($duplicate);
        }

        $marketplaceEquivalent = $this->formulaWidgetDuplicateService->findMarketplaceEquivalentByVariableId(
            $user,
            (int) $data['financial_variable_id'],
            $data['display_type'],
            $data['period_preset'] ?? null,
            $data['chart_config'] ?? null,
        );

        if ($marketplaceEquivalent !== null) {
            $marketplaceEquivalent->loadMissing('financialVariable:id,code,name,type,formula_string');

            return $this->redirectMarketplaceEquivalent($user, $marketplaceEquivalent);
        }

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

    public function edit(Request $request, FormulaWidget $formulaWidget): Response
    {
        $this->authorize('update', $formulaWidget);

        $user = $request->user();
        $formulaWidget->load('financialVariable');
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
            'accounts' => $this->accountOptionsForUser($user),
            'editingWidget' => self::formatWidget($formulaWidget),
        ]);
    }

    public function update(
        UpdateFormulaWidgetRequest $request,
        FormulaWidget $formulaWidget,
    ): RedirectResponse {
        $user = Auth::user();
        $data = $request->validated();

        $duplicate = $this->formulaWidgetDuplicateService->findDuplicateByVariableId(
            $user,
            (int) $data['financial_variable_id'],
            $data['display_type'],
            $data['period_preset'] ?? null,
            $data['chart_config'] ?? null,
            $formulaWidget->id,
        );

        if ($duplicate !== null) {
            $duplicate->loadMissing('financialVariable:id,code,name,type,formula_string');

            return $this->redirectOwnDuplicate($duplicate, 'Esiste già un altro widget con la stessa formula e configurazione grafica.');
        }

        $marketplaceEquivalent = $this->formulaWidgetDuplicateService->findMarketplaceEquivalentByVariableId(
            $user,
            (int) $data['financial_variable_id'],
            $data['display_type'],
            $data['period_preset'] ?? null,
            $data['chart_config'] ?? null,
        );

        if ($marketplaceEquivalent !== null) {
            $marketplaceEquivalent->loadMissing('financialVariable:id,code,name,type,formula_string');

            return $this->redirectMarketplaceEquivalent($user, $marketplaceEquivalent);
        }

        $isPublic = (bool) ($data['is_public'] ?? false);
        $shareToken = $formulaWidget->share_token;
        if ($isPublic && $shareToken === null) {
            $shareToken = $this->generateShareToken();
        }
        if (! $isPublic) {
            $shareToken = null;
        }

        $formulaWidget->update([
            'financial_variable_id' => $data['financial_variable_id'],
            'name' => $data['name'],
            'display_type' => $data['display_type'],
            'period_preset' => $data['period_preset'] ?? null,
            'chart_config' => $data['chart_config'] ?? null,
            'default_size' => $data['default_size'] ?? $formulaWidget->default_size,
            'is_public' => $isPublic,
            'share_token' => $shareToken,
        ]);

        return redirect()
            ->route('formula-widgets.index')
            ->with('success', 'Widget aggiornato con successo.');
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
            'source_id' => $widget->source_id,
            'financial_variable' => $widget->relationLoaded('financialVariable') && $widget->financialVariable
                ? FinancialVariableController::formatVariable($widget->financialVariable)
                : null,
        ];
    }

    private function generateShareToken(): string
    {
        return 'w_'.Str::lower(Str::random(10));
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function accountOptionsForUser(User $user): array
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return [];
        }

        return Account::query()
            ->where('household_id', $householdId)
            ->where('active', true)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('owner_user_id', $user->id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
            ])
            ->values()
            ->all();
    }

    private function redirectOwnDuplicate(
        FormulaWidget $duplicate,
        string $message = 'Hai già un widget con la stessa formula e configurazione grafica.',
    ): RedirectResponse {
        return redirect()
            ->back()
            ->withInput()
            ->withErrors(['widget' => $message])
            ->with('duplicateWidget', self::formatWidget($duplicate));
    }

    private function redirectMarketplaceEquivalent(User $user, FormulaWidget $marketplaceWidget): RedirectResponse
    {
        $label = $marketplaceWidget->is_official_template ? 'template della galleria' : 'widget condiviso dalla community';

        return redirect()
            ->back()
            ->withInput()
            ->withErrors([
                'widget' => "Esiste già un {$label} con la stessa formula e configurazione grafica.",
            ])
            ->with(
                'duplicateMarketplaceWidget',
                FormulaMarketplaceController::formatMarketplaceSuggestion($marketplaceWidget, $user->id),
            );
    }
}
