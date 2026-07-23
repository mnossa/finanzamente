<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\DashboardLayout;
use App\Models\DebtCredit;
use App\Models\FinancialGoal;
use App\Models\FormulaWidget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\AssetClassificationService;
use App\Services\DashboardCacheService;
use App\Services\FinancialMetricsService;
use App\Services\FormulaWidgetBootstrapService;
use App\Services\FormulaWidgetDataVersionService;
use App\Services\FormulaWidgetLayoutNormalizer;
use App\Services\FormulaWidgetPayloadBuilder;
use App\Services\InvestmentLedgerService;
use App\Services\ModuleAccessService;
use App\Services\PacProjectionService;
use App\Services\PortfolioSnapshotService;
use App\Services\UpcomingCashflowService;
use App\Support\DatabaseDialect;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
        private readonly PortfolioSnapshotService $portfolioSnapshotService,
        private readonly FinancialMetricsService $financialMetricsService,
        private readonly FormulaWidgetBootstrapService $formulaWidgetBootstrapService,
        private readonly FormulaWidgetPayloadBuilder $formulaWidgetPayloadBuilder,
        private readonly FormulaWidgetLayoutNormalizer $formulaWidgetLayoutNormalizer,
        private readonly FormulaWidgetDataVersionService $formulaWidgetDataVersionService,
        private readonly DashboardCacheService $dashboardCacheService,
        private readonly PacProjectionService $pacProjectionService,
        private readonly UpcomingCashflowService $upcomingCashflowService,
    ) {}

    /**
     * Mostra la dashboard principale con riepilogo finanziario.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $this->formulaWidgetBootstrapService->provisionForUser($user);

        $activeBoard = $this->resolveActiveBoard($request, $user);
        $canEditLayout = $activeBoard !== null;

        $periodLabel = 'Ultimi 30 giorni';
        $previousPeriodLabel = '30 giorni precedenti';
        $endOfPeriod = Carbon::now()->endOfDay();
        $startOfPeriod = Carbon::now()->subDays(29)->startOfDay();
        $endOfPrevious = Carbon::now()->subDays(30)->endOfDay();
        $startOfPrevious = Carbon::now()->subDays(59)->startOfDay();

        $payload = $this->dashboardCacheService->rememberIndexPayload($user, function () use (
            $user,
            $householdId,
            $startOfPeriod,
            $endOfPeriod,
            $startOfPrevious,
            $endOfPrevious,
            $activeBoard,
        ) {
            return array_merge($this->buildIndexPayload($user, $activeBoard), [
                'periodStats' => $this->getPeriodStats($householdId, $user->id, $startOfPeriod, $endOfPeriod),
                'previousPeriodStats' => $this->getPeriodStats($householdId, $user->id, $startOfPrevious, $endOfPrevious),
            ]);
        }, $activeBoard?->id);

        $boards = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $householdId)
            ->orderByDesc('is_home')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'is_home'])
            ->map(fn (DashboardLayout $board) => [
                'id' => $board->id,
                'name' => $board->name,
                'is_home' => $board->is_home,
            ])
            ->values()
            ->all();

        return Inertia::render('Dashboard', array_merge($payload, [
            'periodLabel' => $periodLabel,
            'previousPeriodLabel' => $previousPeriodLabel,
            'importShareToken' => session('importShareToken'),
            'activeBoard' => $activeBoard ? [
                'id' => $activeBoard->id,
                'name' => $activeBoard->name,
                'is_home' => $activeBoard->is_home,
            ] : null,
            'boards' => $boards,
            'canEditLayout' => $canEditLayout,
            'startEditing' => $canEditLayout && $request->boolean('edit'),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIndexPayload(User $user, ?DashboardLayout $activeBoard = null): array
    {
        $householdId = $user->active_household_id;
        $dashboardLayout = $this->getDashboardLayout($user, $activeBoard);
        $formulaWidgetDataVersion = $this->formulaWidgetDataVersionService->resolveForUser($user);
        $formulaWidgetPayloads = $this->buildPriorityFormulaWidgetPayloads($user, $dashboardLayout);
        $formulaWidgetMeta = $this->buildFormulaWidgetMeta($user, $dashboardLayout);

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->get();

        $accountsWithBalance = $this->accountBalanceService->mapAccountsWithBalance($accounts, $user);

        $totalBalance = $this->accountBalanceService->computeHouseholdTotal($user, $accounts);

        $portfolioSnapshot = $this->portfolioSnapshotService->build($user);
        $balanceBreakdown = [
            'total' => round((float) $totalBalance, 2),
            'invested' => $portfolioSnapshot['investedValue'],
            'investedLinked' => $portfolioSnapshot['investedLinkedValue'],
            'patrimonioTotal' => $portfolioSnapshot['totalValue'],
        ];

        $recentTransactions = Transaction::with(['account', 'category', 'user'])
            ->whereHas('account', function ($query) use ($householdId) {
                $query->where('household_id', $householdId);
            })
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'amount' => (float) $transaction->amount,
                    'date' => $transaction->date->format('Y-m-d'),
                    'description' => $transaction->description,
                    'category' => $transaction->category ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                        'color' => $transaction->category->color,
                        'icon' => $transaction->category->icon,
                    ] : null,
                    'account' => [
                        'id' => $transaction->account->id,
                        'name' => $transaction->account->name,
                    ],
                    'user' => [
                        'id' => $transaction->user->id,
                        'name' => $transaction->user->name,
                    ],
                ];
            });

        $activeBudgets = Budget::where('household_id', $householdId)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->with(['category', 'currency'])
            ->get()
            ->map(function ($budget) use ($householdId) {
                $spent = Transaction::whereHas('account', function ($query) use ($householdId) {
                    $query->where('household_id', $householdId);
                })
                    ->where('category_id', $budget->category_id)
                    ->whereHas('category', function ($query) {
                        $query->where('type', 'expense');
                    })
                    ->whereBetween('date', [$budget->period_start, $budget->period_end])
                    ->sum('amount');

                $percentage = $budget->amount > 0
                    ? min(100, round(($spent / $budget->amount) * 100, 1))
                    : 0;

                return [
                    'id' => $budget->id,
                    'category_name' => $budget->category->name,
                    'category_icon' => $budget->category->icon,
                    'amount' => (float) $budget->amount,
                    'spent' => (float) $spent,
                    'percentage' => $percentage,
                    'is_exceeded' => $spent > $budget->amount,
                    'currency_code' => $budget->currency_code,
                    'currency_symbol' => $budget->currency->symbol,
                ];
            });

        $openDebtsCredits = DebtCredit::where('household_id', $householdId)
            ->whereIn('status', ['open', 'overdue'])
            ->with('currency')
            ->orderByRaw("CASE status WHEN 'overdue' THEN 0 WHEN 'open' THEN 1 ELSE 2 END")
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn ($dc) => [
                'id' => $dc->id,
                'counterparty' => $dc->counterparty,
                'amount' => (float) $dc->getRemainingAmount(),
                'type' => $dc->type,
                'status' => $dc->status,
                'due_date' => $dc->due_date?->format('Y-m-d'),
                'currency_code' => $dc->currency_code,
                'currency_symbol' => $dc->currency->symbol,
            ]);

        $debtsCreditsSummary = [
            'total_debts' => DebtCredit::where('household_id', $householdId)
                ->where('type', 'debt')
                ->whereIn('status', ['open', 'overdue'])
                ->selectRaw('SUM(COALESCE(initial_amount, amount) - COALESCE(paid_amount, 0)) as total')
                ->value('total') ?? 0,
            'total_credits' => DebtCredit::where('household_id', $householdId)
                ->where('type', 'credit')
                ->whereIn('status', ['open', 'overdue'])
                ->selectRaw('SUM(COALESCE(initial_amount, amount) - COALESCE(paid_amount, 0)) as total')
                ->value('total') ?? 0,
            'overdue_count' => DebtCredit::where('household_id', $householdId)
                ->where('status', 'overdue')
                ->count(),
        ];

        return [
            'accounts' => $accountsWithBalance,
            'totalBalance' => $totalBalance,
            'projectedHouseholdBalance' => $this->upcomingCashflowService->projectedHouseholdBalance($user),
            'balanceBreakdown' => $balanceBreakdown,
            'recentTransactions' => $recentTransactions,
            'activeBudgets' => $activeBudgets,
            'openDebtsCredits' => $openDebtsCredits,
            'debtsCreditsSummary' => $debtsCreditsSummary,
            'taxThermometerData' => $this->getTaxThermometerData($user),
            'dashboardLayout' => $dashboardLayout,
            'formulaWidgetPayloads' => $formulaWidgetPayloads,
            'formulaWidgetMeta' => $formulaWidgetMeta,
            'formulaWidgetDataVersion' => $formulaWidgetDataVersion,
            'financialGoals' => $this->getFinancialGoalsData($householdId),
        ];
    }

    /**
     * Widget dashboard caricati dopo il first paint (lifestyle, allocazione, grafici spese).
     */
    public function deferredWidgets(Request $request): JsonResponse
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $payload = $this->dashboardCacheService->rememberDeferredWidgets($user, function () use ($user, $householdId) {
            return [
                'lifestyleWidgetData' => $this->getLifestyleWidgetData($user),
                'assetAllocationData' => $this->getAssetAllocationWidgetData($user),
                'expenseCategories' => $this->getExpenseCategoryData($householdId, $user->id),
                'expenseDistributionData' => $this->getExpenseDistributionData($user, $householdId),
                'pacProjectionData' => $this->getPacProjectionWidgetData($user),
            ];
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Payload dei widget a formula (caricamento differito dopo il first paint della dashboard).
     */
    public function formulaWidgetPayloads(Request $request): JsonResponse|Response
    {
        $user = Auth::user();
        $activeBoard = $this->resolveActiveBoard($request, $user);
        $dashboardLayout = $this->getDashboardLayout($user, $activeBoard);
        $widgetIds = $this->extractVisibleFormulaWidgetIds($dashboardLayout);
        $dataVersion = $this->formulaWidgetDataVersionService->resolveForUser($user);

        if ($widgetIds === []) {
            return response()
                ->json(['payloads' => [], 'dataVersion' => $dataVersion])
                ->header('Cache-Control', 'private, max-age=300');
        }

        $requestedIds = $this->resolveRequestedFormulaWidgetIds($request, $widgetIds);
        $requestParamOverrides = $this->parseRequestRuntimeParams($request);
        $runtimeParamsByWidgetId = $this->mergeRuntimeParamsForWidgets(
            $dashboardLayout,
            $requestParamOverrides,
            $requestedIds,
        );
        $etag = $this->buildFormulaWidgetPayloadsEtag($dataVersion, $widgetIds, $runtimeParamsByWidgetId);
        $isFullPayloadRequest = $requestedIds === $widgetIds;

        if ($isFullPayloadRequest && $request->header('If-None-Match') === $etag) {
            return response()
                ->noContent(304)
                ->header('ETag', $etag)
                ->header('X-Formula-Widget-Data-Version', $dataVersion)
                ->header('Cache-Control', 'private, max-age=300');
        }

        return response()
            ->json([
                'payloads' => $this->dashboardCacheService->rememberFormulaPayloads(
                    $user,
                    $requestedIds,
                    fn () => $this->buildFormulaWidgetPayloadsWithRuntime(
                        $user,
                        $dashboardLayout,
                        $requestedIds,
                        $runtimeParamsByWidgetId,
                    ),
                    $this->runtimeParamsCacheKey($runtimeParamsByWidgetId, $requestedIds),
                ),
                'dataVersion' => $dataVersion,
            ])
            ->header('ETag', $etag)
            ->header('X-Formula-Widget-Data-Version', $dataVersion)
            ->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Recupera i dati per il widget Termometro Tasse.
     */
    private function getTaxThermometerData(User $user): array
    {
        // Il termometro tasse è visibile solo agli utenti con Partita IVA
        if ($user->user_type !== 'partita_iva') {
            return [
                'visible' => false,
                'has_vat' => false,
                'gross_income' => 0,
                'tax_rate' => 15,
                'inps_rate' => 26.23,
            ];
        }

        $settings = $user->profile_settings ?? [];

        // Calcola le entrate lorde dell'anno corrente
        $year = Carbon::now()->year;
        $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::createFromDate($year, 12, 31)->endOfDay();
        $householdId = $user->active_household_id;

        $grossIncome = (float) Transaction::whereHas('account', function ($q) use ($householdId) {
            $q->where('household_id', $householdId);
        })
            ->where('user_id', $user->id)
            ->where('amount', '>', 0)
            ->whereBetween('date', [$startOfYear, $endOfYear])
            ->sum('amount');

        return [
            'visible' => true,
            'has_vat' => true,
            'gross_income' => $grossIncome,
            'tax_rate' => (float) ($settings['tax_rate'] ?? 15),
            'inps_rate' => (float) ($settings['inps_rate'] ?? 26.23),
        ];
    }

    /**
     * Calcola entrate, uscite e conteggio transazioni per un intervallo di date.
     */
    private function getPeriodStats(int $householdId, int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $query = Transaction::whereHas('account', function ($query) use ($householdId) {
            $query->where('household_id', $householdId)
                ->where('active', true);
        })
            ->where(function ($query) use ($userId) {
                $query->where('is_private', false)
                    ->orWhere('user_id', $userId);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNull('transfer_id');

        // Entrate (importi positivi)
        $income = (clone $query)->where('amount', '>', 0)->sum('amount');

        // Uscite (importi negativi, restituiti come valore assoluto)
        $expenses = abs((clone $query)->where('amount', '<', 0)->sum('amount'));

        // Saldo netto del periodo
        $net = $income - $expenses;

        // Numero di transazioni
        $transactionCount = (clone $query)->count();

        return [
            'income' => (float) $income,
            'expenses' => (float) $expenses,
            'net' => (float) $net,
            'transaction_count' => $transactionCount,
        ];
    }

    /**
     * Calcola i dati per il widget Lifestyle Score (storico completo + trend 30 gg).
     *
     * Il widget si sblocca soltanto quando l'utente ha transazioni registrate in
     * almeno 2 mesi di calendario distinti. Prima di allora viene restituito uno
     * stato "locked" con il numero di mesi già coperti, così il frontend può
     * mostrare la challenge di sblocco.
     */
    private function getLifestyleWidgetData(User $user): array
    {
        // Il widget è riservato al piano Pro
        $moduleService = app(ModuleAccessService::class);
        if (! $moduleService->canAccessModuleById($user, 'lifestyle_score')) {
            return [
                'unlocked' => false,
                'months_with_data' => 0,
                'months_needed' => 2,
                'lifestyle_score' => null,
                'net_income' => 0.0,
                'effective_expenses' => 0.0,
                'is_partita_iva' => $user->user_type === 'partita_iva',
                'top_categories' => [],
                'trend' => [
                    'last30_score' => null,
                    'prev30_score' => null,
                    'delta' => null,
                    'direction' => 'unknown',
                ],
            ];
        }

        // ── Verifica mesi distinti con transazioni ───────────────────────────────
        $yearMonthExpr = DatabaseDialect::yearMonthExpr('date');

        $monthsWithData = (int) Transaction::whereHas(
            'account',
            fn ($q) => $q->where('household_id', $user->active_household_id)
        )
            ->whereNull('transfer_id')
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->selectRaw("{$yearMonthExpr} as ym")
            ->distinct()
            ->get()
            ->count();

        $monthsNeeded = 2;
        $unlocked = $monthsWithData >= $monthsNeeded;

        if (! $unlocked) {
            return [
                'unlocked' => false,
                'months_with_data' => $monthsWithData,
                'months_needed' => $monthsNeeded,
                // campi nullable per soddisfare l'interfaccia TS
                'lifestyle_score' => null,
                'net_income' => 0.0,
                'effective_expenses' => 0.0,
                'is_partita_iva' => $user->user_type === 'partita_iva',
                'top_categories' => [],
                'trend' => [
                    'last30_score' => null,
                    'prev30_score' => null,
                    'delta' => null,
                    'direction' => 'unknown',
                ],
            ];
        }

        $service = $this->financialMetricsService;

        // Score sull'intero storico
        $firstTx = Transaction::whereHas(
            'account',
            fn ($q) => $q->where('household_id', $user->active_household_id)
        )->whereNull('transfer_id')->oldest('date')->first();

        $start = $firstTx ? $firstTx->date->startOfDay() : Carbon::now()->startOfMonth();
        $overall = $service->calculate($user, $start, Carbon::now()->endOfDay());

        // Trend: ultimi 30 gg vs 30 gg precedenti
        $last30 = $service->calculate(
            $user,
            Carbon::now()->subDays(29)->startOfDay(),
            Carbon::now()->endOfDay()
        );
        $prev30 = $service->calculate(
            $user,
            Carbon::now()->subDays(59)->startOfDay(),
            Carbon::now()->subDays(30)->endOfDay()
        );

        $last30Score = $last30['lifestyle_score'];
        $prev30Score = $prev30['lifestyle_score'];
        $delta = ($last30Score !== null && $prev30Score !== null)
            ? round($last30Score - $prev30Score, 1)
            : null;
        $direction = match (true) {
            $delta === null && $last30Score !== null => 'new',
            $delta === null => 'unknown',
            $delta > 1.0 => 'up',
            $delta < -1.0 => 'down',
            default => 'stable',
        };

        $topCategories = array_slice(
            array_filter($overall['category_breakdown'], fn ($c) => ! $c['excluded']),
            0,
            5
        );

        return [
            'unlocked' => true,
            'months_with_data' => $monthsWithData,
            'months_needed' => $monthsNeeded,
            'lifestyle_score' => $overall['lifestyle_score'],
            'net_income' => $overall['net_income'],
            'effective_expenses' => $overall['effective_expenses'],
            'is_partita_iva' => $overall['is_partita_iva'],
            'top_categories' => array_values($topCategories),
            'trend' => [
                'last30_score' => $last30Score !== null ? round($last30Score, 1) : null,
                'prev30_score' => $prev30Score !== null ? round($prev30Score, 1) : null,
                'delta' => $delta,
                'direction' => $direction,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboardLayout
     * @return array<string, array<string, mixed>>
     */
    /**
     * @param  list<int>|null  $onlyWidgetIds  Se valorizzato, costruisce solo questi ID (nell'ordine del layout).
     * @return array<string, array<string, mixed>>
     */
    private function buildFormulaWidgetPayloads(User $user, array $dashboardLayout, ?array $onlyWidgetIds = null): array
    {
        $widgetIds = $this->extractVisibleFormulaWidgetIds($dashboardLayout);

        if ($widgetIds === []) {
            return [];
        }

        if ($onlyWidgetIds !== null) {
            $allowed = array_flip($widgetIds);
            $widgetIds = array_values(array_filter(
                $onlyWidgetIds,
                fn (int $id) => isset($allowed[$id]),
            ));
        }

        if ($widgetIds === []) {
            return [];
        }

        $widgetsById = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $widgetIds)
            ->get()
            ->keyBy('id');

        $orderedWidgets = [];
        foreach ($widgetIds as $widgetId) {
            $widget = $widgetsById->get($widgetId);
            if ($widget !== null) {
                $orderedWidgets[] = $widget;
            }
        }

        return $this->formulaWidgetPayloadBuilder->buildMany(
            $orderedWidgets,
            $user,
            $this->extractRuntimeParamsByWidgetId($dashboardLayout),
        );
    }

    /**
     * @param  array<string, mixed>  $dashboardLayout
     * @return array<int, array<string, string>>
     */
    private function extractRuntimeParamsByWidgetId(array $dashboardLayout): array
    {
        $map = [];

        foreach ($dashboardLayout['widgets'] ?? [] as $entry) {
            if (! preg_match('/^formula_widget_(\d+)$/', (string) ($entry['id'] ?? ''), $matches)) {
                continue;
            }

            $params = $entry['runtime_params'] ?? [];
            if (! is_array($params) || $params === []) {
                continue;
            }

            $normalized = [];
            foreach ($params as $key => $value) {
                if (! is_string($key) || $key === '') {
                    continue;
                }

                $normalized[$key] = is_scalar($value) ? (string) $value : '';
            }

            if ($normalized !== []) {
                $map[(int) $matches[1]] = $normalized;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $dashboardLayout
     * @param  array<int, array<string, string>>  $requestOverrides
     * @return array<int, array<string, string>>
     */
    private function mergeRuntimeParamsForWidgets(
        array $dashboardLayout,
        array $requestOverrides,
        array $widgetIds,
    ): array {
        $merged = $this->extractRuntimeParamsByWidgetId($dashboardLayout);

        foreach ($widgetIds as $widgetId) {
            if (! isset($requestOverrides[$widgetId]) || ! is_array($requestOverrides[$widgetId])) {
                continue;
            }

            $merged[$widgetId] = array_merge(
                $merged[$widgetId] ?? [],
                array_map(static fn ($value) => is_scalar($value) ? (string) $value : '', $requestOverrides[$widgetId]),
            );
        }

        return $merged;
    }

    /**
     * @param  list<int>  $layoutWidgetIds
     * @return list<int>
     */
    private function resolveRequestedFormulaWidgetIds(Request $request, array $layoutWidgetIds): array
    {
        $rawIds = $request->query('ids');
        if (! is_string($rawIds) || trim($rawIds) === '') {
            return $layoutWidgetIds;
        }

        $requested = [];
        foreach (explode(',', $rawIds) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part)) {
                $requested[] = (int) $part;
            }
        }

        if ($requested === []) {
            return $layoutWidgetIds;
        }

        $allowed = array_flip($layoutWidgetIds);

        return array_values(array_filter(
            $requested,
            fn (int $id) => isset($allowed[$id]),
        ));
    }

    /**
     * @param  list<int>  $widgetIds
     * @param  array<int, array<string, string>>  $runtimeParamsByWidgetId
     */
    private function buildFormulaWidgetPayloadsWithRuntime(
        User $user,
        array $dashboardLayout,
        array $widgetIds,
        array $runtimeParamsByWidgetId,
    ): array {
        if ($widgetIds === []) {
            return [];
        }

        $widgetsById = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $widgetIds)
            ->get()
            ->keyBy('id');

        $orderedWidgets = [];
        foreach ($widgetIds as $widgetId) {
            $widget = $widgetsById->get($widgetId);
            if ($widget !== null) {
                $orderedWidgets[] = $widget;
            }
        }

        $filteredRuntimeParams = array_intersect_key(
            $runtimeParamsByWidgetId,
            array_flip($widgetIds),
        );

        return $this->formulaWidgetPayloadBuilder->buildMany(
            $orderedWidgets,
            $user,
            $filteredRuntimeParams,
        );
    }

    /**
     * @param  list<int>  $layoutWidgetIds
     * @param  array<int, array<string, string>>  $runtimeParamsByWidgetId
     */
    private function buildFormulaWidgetPayloadsEtag(
        string $dataVersion,
        array $layoutWidgetIds,
        array $runtimeParamsByWidgetId = [],
    ): string {
        $runtimeKey = $this->runtimeParamsCacheKey($runtimeParamsByWidgetId, $layoutWidgetIds);
        $source = $dataVersion.'|'.implode(',', $layoutWidgetIds).'|'.$runtimeKey;

        return '"'.md5($source).'"';
    }

    /**
     * @param  array<int, array<string, string>>  $runtimeParamsByWidgetId
     * @param  list<int>  $widgetIds
     */
    private function runtimeParamsCacheKey(array $runtimeParamsByWidgetId, array $widgetIds): string
    {
        $parts = [];
        foreach ($widgetIds as $widgetId) {
            $params = $runtimeParamsByWidgetId[$widgetId] ?? [];
            if ($params === []) {
                continue;
            }

            ksort($params);
            $encoded = [];
            foreach ($params as $key => $value) {
                $encoded[] = $key.'='.$value;
            }

            $parts[] = $widgetId.':'.implode(',', $encoded);
        }

        return implode(';', $parts);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseRequestRuntimeParams(Request $request): array
    {
        $raw = $request->query('params');
        if (! is_array($raw)) {
            return [];
        }

        $parsed = [];
        foreach ($raw as $widgetId => $params) {
            if (! ctype_digit((string) $widgetId) || ! is_array($params)) {
                continue;
            }

            $normalized = [];
            foreach ($params as $key => $value) {
                if (! is_string($key) || $key === '' || ! is_scalar($value)) {
                    continue;
                }

                $normalized[$key] = (string) $value;
            }

            if ($normalized !== []) {
                $parsed[(int) $widgetId] = $normalized;
            }
        }

        return $parsed;
    }

    /**
     * Payload SSR per i primi widget formula visibili (above-the-fold) — migliora LCP senza bloccare il first paint completo.
     *
     * @param  array<string, mixed>  $dashboardLayout
     * @return array<string, array<string, mixed>>
     */
    private function buildPriorityFormulaWidgetPayloads(User $user, array $dashboardLayout, int $limit = 4): array
    {
        $widgetIds = $this->extractVisibleFormulaWidgetIds($dashboardLayout);

        if ($widgetIds === []) {
            return [];
        }

        $widgetsById = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $widgetIds)
            ->get()
            ->keyBy('id');

        $orderedWidgets = collect($widgetIds)
            ->map(fn (int $id) => $widgetsById->get($id))
            ->filter()
            ->values();

        $lightWidgets = $orderedWidgets
            ->filter(fn (FormulaWidget $widget) => in_array($widget->display_type, ['kpi', 'progress'], true))
            ->take($limit);

        if ($lightWidgets->isEmpty()) {
            return [];
        }

        return $this->formulaWidgetPayloadBuilder->buildMany(
            $lightWidgets->values()->all(),
            $user,
            $this->extractRuntimeParamsByWidgetId($dashboardLayout),
        );
    }

    /**
     * @param  array<string, mixed>  $dashboardLayout
     * @return list<int>
     */
    private function extractVisibleFormulaWidgetIds(array $dashboardLayout): array
    {
        $entries = [];

        foreach ($dashboardLayout['widgets'] ?? [] as $entry) {
            if (! ($entry['visible'] ?? false)) {
                continue;
            }

            $layoutId = (string) ($entry['id'] ?? '');
            if (! preg_match('/^formula_widget_(\d+)$/', $layoutId, $matches)) {
                continue;
            }

            $entries[] = [
                'id' => (int) $matches[1],
                'position' => (int) ($entry['position'] ?? 0),
            ];
        }

        usort($entries, fn (array $a, array $b) => $a['position'] <=> $b['position']);

        return array_column($entries, 'id');
    }

    /**
     * @param  array<string, mixed>  $dashboardLayout
     * @return array<string, array{name: string, display_type: string, variant: string|null}>
     */
    private function buildFormulaWidgetMeta(User $user, array $dashboardLayout): array
    {
        $widgetIds = [];
        foreach ($dashboardLayout['widgets'] ?? [] as $entry) {
            $id = $entry['id'] ?? '';
            if (preg_match('/^formula_widget_(\d+)$/', (string) $id, $matches)) {
                $widgetIds[] = (int) $matches[1];
            }
        }

        if ($widgetIds === []) {
            return [];
        }

        $meta = [];
        FormulaWidget::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $widgetIds)
            ->with('source:id,is_official_template')
            ->get(['id', 'name', 'display_type', 'chart_config', 'source_id', 'is_official_template'])
            ->each(function (FormulaWidget $widget) use (&$meta) {
                $chartConfig = $widget->chart_config ?? [];

                $meta[(string) $widget->id] = [
                    'name' => $widget->name,
                    'display_type' => $widget->display_type,
                    'variant' => is_string($chartConfig['variant'] ?? null) ? $chartConfig['variant'] : null,
                    'can_delete' => ! $widget->isOfficialProtected(),
                ];
            });

        return $meta;
    }

    /**
     * Resolve board from ?board= or Home (create Home se manca).
     */
    private function resolveActiveBoard(Request $request, User $user): ?DashboardLayout
    {
        $householdId = $user->active_household_id;
        $boardId = $request->integer('board') ?: null;

        if ($boardId) {
            $board = DashboardLayout::findOwned($user->id, $householdId, $boardId);
            abort_if($board === null, 404);

            return $board;
        }

        $home = DashboardLayout::findHome($user->id, $householdId);

        if ($home !== null || $householdId === null) {
            return $home;
        }

        return DashboardLayout::create([
            'user_id' => $user->id,
            'household_id' => $householdId,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfigForUser($user),
        ]);
    }

    /**
     * Layout della board attiva (Home o custom).
     */
    private function getDashboardLayout(User $user, ?DashboardLayout $board = null): array
    {
        if ($board === null) {
            return DashboardLayout::essentialConfig();
        }

        $savedConfig = DashboardLayout::stripUnsupportedWidgets($board->config);

        if ($board->is_home) {
            if (DashboardLayout::isBareEssentialConfig($savedConfig)) {
                $healed = DashboardLayout::essentialConfigForUser($user);
                $board->update(['config' => $healed]);

                return $healed;
            }

            $sanitized = $this->formulaWidgetLayoutNormalizer->sanitizeFormulaWidgets($user, $savedConfig);
        } else {
            // Board custom: rispetta config salvata (template «Vuota» = zero widget).
            // I formula si aggiungono solo via pin esplicito / template alla creazione.
            $sanitized = $this->formulaWidgetLayoutNormalizer->sanitizeFormulaWidgets($user, $savedConfig);
        }

        if (array_column($board->config['widgets'] ?? [], 'id') !== array_column($sanitized['widgets'] ?? [], 'id')) {
            $board->update(['config' => $sanitized]);
        }

        return $this->formulaWidgetLayoutNormalizer->normalize($user, $sanitized);
    }

    /**
     * Dati sintetici per il widget Asset Allocation in dashboard.
     * Restituisce il valore totale, l'allocazione per classe e l'indice di rischio.
     * Utilizza AssetClassificationService per i mapping e un'aggregazione unica
     * delle transazioni per evitare query N+1.
     */
    private function getAssetAllocationWidgetData(User $user): array
    {
        $snapshot = $this->portfolioSnapshotService->build($user);

        return [
            'total_value' => $snapshot['allocationTotalValue'],
            'risk_index' => $snapshot['allocationRiskIndex'],
            'risk_label' => $snapshot['allocationRiskLabel'],
            'allocation' => $snapshot['allocation'],
        ];
    }

    private function getPacProjectionWidgetData(User $user): array
    {
        return $this->pacProjectionService->buildHouseholdProjection($user, 12);
    }

    /**
     * Calcola le spese per categoria nel mese corrente.
     */
    private function getExpenseCategoryData(int $householdId, int $userId): array
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfDay();

        $expenses = Transaction::with('category')
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereBetween('date', [$startDate, $endDate])
            ->where('amount', '<', 0)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(ABS(amount)) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $grandTotal = $expenses->sum('total');

        return $expenses->map(function ($row) use ($grandTotal) {
            $category = $row->category;

            return [
                'name' => $category?->name ?? 'Senza categoria',
                'value' => round((float) $row->total, 2),
                'percentage' => $grandTotal > 0 ? round(((float) $row->total / (float) $grandTotal) * 100, 1) : 0,
                'color' => $category?->color ?? '#94a3b8',
                'icon' => $category?->icon ?? '📁',
                'category_id' => $row->category_id,
            ];
        })->values()->toArray();
    }

    private function getFinancialGoalsData(int $householdId): array
    {
        return FinancialGoal::where('household_id', $householdId)
            ->where('status', 'in_progress')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($goal) => [
                'id' => $goal->id,
                'name' => $goal->name,
                'icon' => $goal->icon,
                'color' => $goal->color,
                'target_amount' => (float) $goal->target_amount,
                'current_amount' => (float) $goal->current_amount,
                'currency_code' => $goal->currency_code,
                'target_date' => $goal->target_date?->format('Y-m-d'),
                'percentage' => $goal->target_amount > 0
                    ? min(100, round(((float) $goal->current_amount / (float) $goal->target_amount) * 100, 1))
                    : 0,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Calcola i dati per il widget Distribuzione Spese (Necessità / Extra / Investimenti).
     *
     * Le spese del mese corrente vengono aggregate per categoria e poi raggruppate
     * in base al campo `expense_distribution` di ciascuna categoria.
     * Le soglie personalizzate vengono lette da `profile_settings` dell'utente.
     * Le categorie non classificate vengono restituite separatamente così il frontend
     * può suggerire all'utente di classificarle.
     */
    private function getExpenseDistributionData(User $user, int $householdId): array
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        // Recupera soglie personalizzate da profile_settings (default 50/30/20)
        $settings = $user->profile_settings ?? [];
        $thresholds = $settings['expense_distribution_thresholds'] ?? [
            'needs' => 50,
            'wants' => 30,
            'investments' => 20,
        ];

        // Aggrega le spese per categoria nel mese corrente
        $expenses = Transaction::with('category')
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->whereBetween('date', [$startDate, $endDate])
            ->where('amount', '<', 0)
            ->whereNull('transfer_id')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(ABS(amount)) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $totalExpenses = (float) $expenses->sum('total');

        // Bucket iniziali
        $buckets = [
            'needs' => ['amount' => 0.0, 'categories' => []],
            'wants' => ['amount' => 0.0, 'categories' => []],
            'investments' => ['amount' => 0.0, 'categories' => []],
            'unclassified' => ['amount' => 0.0, 'categories' => []],
        ];

        foreach ($expenses as $row) {
            $category = $row->category;
            $amount = (float) $row->total;
            $dist = $category?->expense_distribution ?? null;
            $key = in_array($dist, ['needs', 'wants', 'investments'], true) ? $dist : 'unclassified';

            $buckets[$key]['amount'] += $amount;
            $buckets[$key]['categories'][] = [
                'id' => $category?->id,
                'name' => $category?->name ?? 'Senza categoria',
                'icon' => $category?->icon ?? '📁',
                'color' => $category?->color ?? '#94a3b8',
                'amount' => round($amount, 2),
                'percentage' => 0,
            ];
        }

        // Acquisti da sezione Investimenti senza transazione collegata (es. PAC senza conto)
        $unsynced = app(InvestmentLedgerService::class)->unsyncedPurchasesInPeriod($user, $startDate, $endDate);
        if ($unsynced['amount'] > 0) {
            $totalExpenses += $unsynced['amount'];
            $buckets['investments']['amount'] += $unsynced['amount'];
            foreach ($unsynced['items'] as $item) {
                $buckets['investments']['categories'][] = [
                    'id' => null,
                    'name' => $item['name'].' (movimento investimenti)',
                    'icon' => '📈',
                    'color' => '#6366f1',
                    'amount' => $item['amount'],
                    'percentage' => 0,
                ];
            }
        }

        foreach ($buckets as $bucketKey => $bucket) {
            foreach ($buckets[$bucketKey]['categories'] as $index => $categoryRow) {
                $catAmount = (float) $categoryRow['amount'];
                $buckets[$bucketKey]['categories'][$index]['percentage'] = $totalExpenses > 0
                    ? round(($catAmount / $totalExpenses) * 100, 1)
                    : 0;
            }
        }

        // Costruisce il risultato finale con percentuali e flag di superamento soglia
        $result = [];
        foreach (['needs', 'wants', 'investments'] as $key) {
            $amount = round($buckets[$key]['amount'], 2);
            $percentage = $totalExpenses > 0 ? round(($amount / $totalExpenses) * 100, 1) : 0;
            $threshold = (float) ($thresholds[$key] ?? 0);

            $result[$key] = [
                'amount' => $amount,
                'percentage' => $percentage,
                'threshold' => $threshold,
                'exceeded' => $threshold > 0 && $percentage > $threshold,
                'categories' => $buckets[$key]['categories'],
            ];
        }

        $unclassifiedAmount = round($buckets['unclassified']['amount'], 2);

        return [
            'needs' => $result['needs'],
            'wants' => $result['wants'],
            'investments' => $result['investments'],
            'unclassified' => [
                'amount' => $unclassifiedAmount,
                'percentage' => $totalExpenses > 0 ? round(($unclassifiedAmount / $totalExpenses) * 100, 1) : 0,
                'categories' => $buckets['unclassified']['categories'],
            ],
            'total_expenses' => round($totalExpenses, 2),
            'thresholds' => [
                'needs' => (float) ($thresholds['needs'] ?? 50),
                'wants' => (float) ($thresholds['wants'] ?? 30),
                'investments' => (float) ($thresholds['investments'] ?? 20),
            ],
            'has_custom_thresholds' => isset($settings['expense_distribution_thresholds']),
            'current_month' => Carbon::now()->translatedFormat('F Y'),
        ];
    }
}
