<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewFormulaWidgetRequest;
use App\Http\Requests\StoreFormulaWidgetRequest;
use App\Http\Requests\UpdateFormulaWidgetRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DashboardLayout;
use App\Models\DebtCredit;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Tag;
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
            ->with(['financialVariable:id,code,name,type', 'source:id,is_official_template,template_slug'])
            ->withCount('clones')
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
            'tags' => $this->tagOptionsForUser($user),
            'categories' => $this->categoryOptionsForUser($user),
            'currencies' => $this->currencyOptions(),
            'debtsCredits' => $this->debtCreditOptionsForUser($user),
            'metricQueryConfig' => config('metric_queries'),
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
            $pinResult = $pinService->pin($user, $widget);
            if ($pinResult === FormulaWidgetDashboardPinService::RESULT_NEEDS_BOARD_CHOICE) {
                return redirect()
                    ->route('formula-widgets.pin.choose', $widget)
                    ->with('success', 'Widget creato. Scegli in quale dashboard aggiungerlo.');
            }
            if ($pinResult === FormulaWidgetDashboardPinService::RESULT_NO_CUSTOM_BOARD) {
                return redirect()
                    ->route('dashboard.boards.index')
                    ->with('success', 'Widget creato. Crea una dashboard per aggiungerlo.');
            }
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
            'tags' => $this->tagOptionsForUser($user),
            'categories' => $this->categoryOptionsForUser($user),
            'currencies' => $this->currencyOptions(),
            'debtsCredits' => $this->debtCreditOptionsForUser($user),
            'metricQueryConfig' => config('metric_queries'),
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

        $undo = $removalService->remove(Auth::user(), $formulaWidget);

        return redirect()
            ->back()
            ->with('success', 'Widget rimosso. Puoi annullare entro 30 secondi.')
            ->with('undoFormulaWidget', $undo);
    }

    public function restore(int $formulaWidget, FormulaWidgetRemovalService $removalService): RedirectResponse
    {
        $widget = FormulaWidget::withTrashed()->findOrFail($formulaWidget);

        $this->authorize('restore', $widget);

        $removalService->restore(Auth::user(), $widget);

        return redirect()
            ->back()
            ->with('success', 'Eliminazione annullata. Widget ripristinato.');
    }

    public function choosePinBoard(FormulaWidget $formulaWidget, FormulaWidgetDashboardPinService $pinService): Response|RedirectResponse
    {
        $this->authorize('view', $formulaWidget);

        $user = Auth::user();

        if ((int) $formulaWidget->user_id !== (int) $user->id) {
            abort(403);
        }

        $householdId = $user->active_household_id;
        if ($householdId === null) {
            return redirect()
                ->route('dashboard.boards.index')
                ->withErrors(['board' => 'Nessuna dashboard disponibile.']);
        }

        $boards = $pinService->listBoards($user->id, $householdId);

        if ($boards->isEmpty()) {
            return redirect()
                ->route('dashboard.boards.index')
                ->withErrors(['board' => 'Nessuna dashboard disponibile.']);
        }

        if ($boards->count() === 1) {
            $pinService->pinToBoard($boards->first(), $formulaWidget);

            return redirect()
                ->route('dashboard', $boards->first()->is_home ? [] : ['board' => $boards->first()->id])
                ->with('success', 'Widget aggiunto alla dashboard.');
        }

        return Inertia::render('FormulaWidgets/PinToBoard', [
            'widget' => self::formatWidget($formulaWidget),
            'boards' => $boards->map(fn (DashboardLayout $board) => [
                'id' => $board->id,
                'name' => $board->name,
                'is_home' => $board->is_home,
            ])->values()->all(),
            'defaultBoardId' => $pinService->defaultBoardId($user->id, $householdId),
        ]);
    }

    public function pin(Request $request, FormulaWidget $formulaWidget, FormulaWidgetDashboardPinService $pinService): RedirectResponse
    {
        $this->authorize('view', $formulaWidget);

        $user = Auth::user();

        if ((int) $formulaWidget->user_id !== (int) $user->id) {
            abort(403);
        }

        $boardId = $request->filled('board_id') ? $request->integer('board_id') : null;
        $pinResult = $pinService->pin($user, $formulaWidget, $boardId);

        if ($pinResult === FormulaWidgetDashboardPinService::RESULT_NEEDS_BOARD_CHOICE) {
            return redirect()->route('formula-widgets.pin.choose', $formulaWidget);
        }

        if ($pinResult === FormulaWidgetDashboardPinService::RESULT_NO_CUSTOM_BOARD) {
            return redirect()
                ->route('dashboard.boards.index')
                ->withErrors([
                    'board' => 'Nessuna dashboard disponibile per aggiungere il widget.',
                ]);
        }

        $target = $pinService->resolvePinnedBoard($user, $formulaWidget, $boardId);

        return redirect()
            ->route('dashboard', $target && ! $target->is_home ? ['board' => $target->id] : [])
            ->with('success', $pinResult === FormulaWidgetDashboardPinService::RESULT_ALREADY
                ? 'Il widget è già presente sulla dashboard selezionata.'
                : 'Widget aggiunto alla dashboard.');
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
            'is_official_template' => $widget->is_official_template,
            'is_official_origin' => $widget->isOfficialProtected(),
            'can_delete' => ! $widget->isOfficialProtected(),
            'clones_count' => (int) ($widget->clones_count ?? $widget->clones()->count()),
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

    /**
     * @return list<array{id: int, name: string}>
     */
    private function tagOptionsForUser(User $user): array
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return [];
        }

        return Tag::query()
            ->forUser($user->id, $householdId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, type: string}>
     */
    private function categoryOptionsForUser(User $user): array
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return [];
        }

        return Category::query()
            ->forHousehold($householdId)
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, name: string, symbol: string}>
     */
    private function currencyOptions(): array
    {
        return Currency::query()
            ->orderBy('code')
            ->get(['code', 'name', 'symbol'])
            ->map(fn (Currency $currency) => [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, counterparty: string, type: string}>
     */
    private function debtCreditOptionsForUser(User $user): array
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return [];
        }

        return DebtCredit::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->whereIn('status', ['open', 'overdue'])
            ->orderBy('counterparty')
            ->get(['id', 'counterparty', 'type'])
            ->map(fn (DebtCredit $dc) => [
                'id' => $dc->id,
                'counterparty' => $dc->counterparty,
                'type' => $dc->type,
            ])
            ->values()
            ->all();
    }
}
