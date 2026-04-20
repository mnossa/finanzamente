<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\DashboardLayout;
use App\Models\DebtCredit;
use App\Models\FinancialGoal;
use App\Models\Investment;
use App\Models\Transaction;
use App\Services\AssetClassificationService;
use App\Services\BudgetNotificationService;
use App\Services\FinancialMetricsService;
use App\Services\RevenueNotificationService;
use App\Services\TransactionTrendNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Mostra la dashboard principale con riepilogo finanziario.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        // Recupera i conti della household (escludendo quelli privati di altri utenti)
        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->get();

        // Calcola saldo totale per ogni conto (saldo iniziale + somma transazioni)
        $accountsWithBalance = $accounts->map(function ($account) use ($user) {
            $transactionsSum = Transaction::where('account_id', $account->id)
                ->where(function ($query) use ($user) {
                    $query->where('is_private', false)
                        ->orWhere('user_id', $user->id);
                })
                ->sum('amount');

            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency_code' => $account->currency_code,
                'initial_balance' => (float) $account->initial_balance,
                'current_balance' => (float) $account->initial_balance + (float) $transactionsSum,
                'is_private' => $account->is_private,
            ];
        });

        // Saldo totale (somma di tutti i conti in EUR)
        $totalBalance = $accountsWithBalance->sum('current_balance');

        // Transazioni recenti (ultime 10)
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

        // Statistiche mensili (mese corrente)
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $monthlyStats = $this->getMonthlyStats($householdId, $user->id, $startOfMonth, $endOfMonth);

        // Statistiche mese precedente (per confronto)
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        $lastMonthStats = $this->getMonthlyStats($householdId, $user->id, $startOfLastMonth, $endOfLastMonth);

        // Controlla e crea notifiche per budget e trend di spesa/entrate
        (new BudgetNotificationService())->checkAndNotify($user, $householdId);
        (new TransactionTrendNotificationService())->checkAndNotify(
            $user,
            $monthlyStats,
            $lastMonthStats,
            Carbon::now()->translatedFormat('F Y'),
            Carbon::now()->subMonth()->translatedFormat('F Y')
        );

        // Budget attivi (con spesa calcolata)
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
                    'currency_symbol' => $budget->currency->symbol,
                ];
            });

        // Debiti e Crediti aperti
        $openDebtsCredits = DebtCredit::where('household_id', $householdId)
            ->whereIn('status', ['open', 'overdue'])
            ->with('currency')
            ->orderByRaw("CASE status WHEN 'overdue' THEN 0 WHEN 'open' THEN 1 ELSE 2 END")
            ->orderBy('due_date')
            ->limit(5)
            ->get()
            ->map(fn($dc) => [
                'id' => $dc->id,
                'counterparty' => $dc->counterparty,
                'amount' => (float) $dc->amount,
                'type' => $dc->type,
                'status' => $dc->status,
                'due_date' => $dc->due_date?->format('Y-m-d'),
                'currency_symbol' => $dc->currency->symbol,
            ]);

        // Totali debiti e crediti
        $debtsCreditsSummary = [
            'total_debts' => DebtCredit::where('household_id', $householdId)
                ->where('type', 'debt')
                ->whereIn('status', ['open', 'overdue'])
                ->sum('amount'),
            'total_credits' => DebtCredit::where('household_id', $householdId)
                ->where('type', 'credit')
                ->whereIn('status', ['open', 'overdue'])
                ->sum('amount'),
            'overdue_count' => DebtCredit::where('household_id', $householdId)
                ->where('status', 'overdue')
                ->count(),
        ];

        return Inertia::render('Dashboard', [
            'accounts' => $accountsWithBalance,
            'totalBalance' => $totalBalance,
            'recentTransactions' => $recentTransactions,
            'monthlyStats' => $monthlyStats,
            'lastMonthStats' => $lastMonthStats,
            'currentMonth' => Carbon::now()->translatedFormat('F Y'),
            'lastMonth' => Carbon::now()->subMonth()->translatedFormat('F Y'),
            'activeBudgets' => $activeBudgets,
            'openDebtsCredits' => $openDebtsCredits,
            'debtsCreditsSummary' => $debtsCreditsSummary,
            'annualRevenueData' => $this->getAnnualRevenueData($user),
            'taxThermometerData' => $this->getTaxThermometerData($user),
            'lifestyleWidgetData' => $this->getLifestyleWidgetData($user),
            'dashboardLayout' => $this->getDashboardLayout($user),
            'assetAllocationData' => $this->getAssetAllocationWidgetData($user),
            'netWorthData' => $this->getNetWorthData($householdId, $user->id),
            'cashFlowData' => $this->getCashFlowData($householdId, $user->id),
            'expenseCategories' => $this->getExpenseCategoryData($householdId, $user->id),
            'financialGoals' => $this->getFinancialGoalsData($householdId),
            'expenseDistributionData' => $this->getExpenseDistributionData($user, $householdId),
        ]);
    }

    /**
     * Recupera i dati per il widget Termometro Tasse.
     */
    private function getTaxThermometerData(\App\Models\User $user): array
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
     * Calcola il fatturato annuo e controlla le notifiche di soglia.
     */
    private function getAnnualRevenueData(\App\Models\User $user): array
    {
        // Il widget fatturato è visibile solo agli utenti con Partita IVA
        if ($user->user_type !== 'partita_iva') {
            return [
                'visible' => false,
                'has_vat' => false,
                'revenue_tracking_enabled' => false,
                'annual_revenue' => 0,
                'revenue_threshold' => 85000,
                'revenue_percentage' => 0,
            ];
        }

        $settings = $user->profile_settings ?? [];
        $trackingEnabled = $settings['revenue_tracking_enabled'] ?? true;
        $threshold = (float) ($settings['revenue_threshold'] ?? 85000);

        if (!$trackingEnabled) {
            return [
                'visible' => true,
                'has_vat' => true,
                'revenue_tracking_enabled' => false,
                'annual_revenue' => 0,
                'revenue_threshold' => $threshold,
                'revenue_percentage' => 0,
            ];
        }

        $year = Carbon::now()->year;
        $startOfYear = Carbon::createFromDate($year, 1, 1)->startOfDay();
        $endOfYear = Carbon::createFromDate($year, 12, 31)->endOfDay();
        $householdId = $user->active_household_id;

        $annualRevenue = (float) Transaction::whereHas('account', function ($q) use ($householdId) {
                $q->where('household_id', $householdId);
            })
            ->where('user_id', $user->id)
            ->where('amount', '>', 0)
            ->whereBetween('date', [$startOfYear, $endOfYear])
            ->sum('amount');

        $percentage = $threshold > 0
            ? round(($annualRevenue / $threshold) * 100, 1)
            : 0;

        // Controlla e crea notifiche se necessario
        (new RevenueNotificationService())->checkAndNotify($user, $annualRevenue, $threshold);

        return [
            'visible' => true,
            'has_vat' => true,
            'revenue_tracking_enabled' => true,
            'annual_revenue' => $annualRevenue,
            'revenue_threshold' => $threshold,
            'revenue_percentage' => $percentage,
        ];
    }

    /**
     * Calcola le statistiche per un determinato periodo.
     */
    private function getMonthlyStats(int $householdId, int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $query = Transaction::whereHas('account', function ($query) use ($householdId) {
            $query->where('household_id', $householdId);
        })
            ->where(function ($query) use ($userId) {
                $query->where('is_private', false)
                    ->orWhere('user_id', $userId);
            })
            ->whereBetween('date', [$startDate, $endDate]);

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
    private function getLifestyleWidgetData(\App\Models\User $user): array
    {
        // Il widget è riservato al piano Pro
        $moduleService = app(\App\Services\ModuleAccessService::class);
        if (! $moduleService->canAccessModuleById($user, 'lifestyle_score')) {
            return [
                'unlocked'           => false,
                'months_with_data'   => 0,
                'months_needed'      => 2,
                'lifestyle_score'    => null,
                'net_income'         => 0.0,
                'effective_expenses' => 0.0,
                'is_partita_iva'     => $user->user_type === 'partita_iva',
                'top_categories'     => [],
                'trend' => [
                    'last30_score' => null,
                    'prev30_score' => null,
                    'delta'        => null,
                    'direction'    => 'unknown',
                ],
            ];
        }

        // ── Verifica mesi distinti con transazioni ───────────────────────────────
        // Compatibile con MySQL (produzione) e SQLite (test in-memory)
        $driver         = \Illuminate\Support\Facades\DB::getDriverName();
        $yearMonthExpr  = $driver === 'sqlite'
            ? "strftime('%Y-%m', date)"
            : "DATE_FORMAT(date, '%Y-%m')";

        $monthsWithData = (int) \App\Models\Transaction::whereHas(
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
        $unlocked     = $monthsWithData >= $monthsNeeded;

        if (! $unlocked) {
            return [
                'unlocked'        => false,
                'months_with_data' => $monthsWithData,
                'months_needed'   => $monthsNeeded,
                // campi nullable per soddisfare l'interfaccia TS
                'lifestyle_score'    => null,
                'net_income'         => 0.0,
                'effective_expenses' => 0.0,
                'is_partita_iva'     => $user->user_type === 'partita_iva',
                'top_categories'     => [],
                'trend' => [
                    'last30_score' => null,
                    'prev30_score' => null,
                    'delta'        => null,
                    'direction'    => 'unknown',
                ],
            ];
        }

        $service = new FinancialMetricsService();

        // Score sull'intero storico
        $firstTx = \App\Models\Transaction::whereHas(
            'account',
            fn ($q) => $q->where('household_id', $user->active_household_id)
        )->whereNull('transfer_id')->oldest('date')->first();

        $start   = $firstTx ? $firstTx->date->startOfDay() : Carbon::now()->startOfMonth();
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
        $delta       = ($last30Score !== null && $prev30Score !== null)
            ? round($last30Score - $prev30Score, 1)
            : null;
        $direction   = match (true) {
            $delta === null && $last30Score !== null => 'new',
            $delta === null                          => 'unknown',
            $delta > 1.0                             => 'up',
            $delta < -1.0                            => 'down',
            default                                  => 'stable',
        };

        $topCategories = array_slice(
            array_filter($overall['category_breakdown'], fn ($c) => !$c['excluded']),
            0,
            5
        );

        return [
            'unlocked'           => true,
            'months_with_data'   => $monthsWithData,
            'months_needed'      => $monthsNeeded,
            'lifestyle_score'    => $overall['lifestyle_score'],
            'net_income'         => $overall['net_income'],
            'effective_expenses' => $overall['effective_expenses'],
            'is_partita_iva'     => $overall['is_partita_iva'],
            'top_categories'     => array_values($topCategories),
            'trend' => [
                'last30_score' => $last30Score !== null ? round($last30Score, 1) : null,
                'prev30_score' => $prev30Score !== null ? round($prev30Score, 1) : null,
                'delta'        => $delta,
                'direction'    => $direction,
            ],
        ];
    }

    /**
     * Recupera la configurazione layout della dashboard per l'utente corrente.
     * Se non esiste una configurazione salvata, restituisce quella di default.
     * I widget nuovi (non presenti nel layout salvato) vengono aggiunti in coda
     * in modo che gli utenti esistenti vedano i nuovi widget automaticamente.
     */
    private function getDashboardLayout(\App\Models\User $user): array
    {
        $layout = DashboardLayout::where('user_id', $user->id)
            ->where('household_id', $user->active_household_id)
            ->first();

        if (! $layout) {
            return DashboardLayout::defaultConfig();
        }

        $savedConfig  = $layout->config;
        $defaultWidgets = DashboardLayout::defaultConfig()['widgets'];

        // Ricava gli ID già presenti nel layout salvato
        $existingIds = array_column($savedConfig['widgets'] ?? [], 'id');
        $maxPosition = empty($savedConfig['widgets']) ? -1 : max(array_column($savedConfig['widgets'], 'position'));

        // Aggiungi in coda i widget del default che non sono ancora presenti
        foreach ($defaultWidgets as $defaultWidget) {
            if (! in_array($defaultWidget['id'], $existingIds, true)) {
                $maxPosition++;
                $savedConfig['widgets'][] = array_merge($defaultWidget, ['position' => $maxPosition]);
            }
        }

        return $savedConfig;
    }

    /**
     * Dati sintetici per il widget Asset Allocation in dashboard.
     * Restituisce il valore totale, l'allocazione per classe e l'indice di rischio.
     * Utilizza AssetClassificationService per i mapping e un'aggregazione unica
     * delle transazioni per evitare query N+1.
     */
    private function getAssetAllocationWidgetData(\App\Models\User $user): array
    {
        $householdId = $user->active_household_id;

        // Investimenti aperti
        $investments = Investment::with('asset')
            ->where('household_id', $householdId)
            ->whereNull('sell_date')
            ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->get();

        $classValues   = [];
        $riskNumerator = 0.0;
        $totalValue    = 0.0;

        foreach ($investments as $inv) {
            $type = $inv->asset->type ?? 'other';
            $cls  = AssetClassificationService::ASSET_TYPE_CLASS[$type] ?? 'other';
            $risk = AssetClassificationService::ASSET_TYPE_RISK[$type] ?? 3;
            $val  = $inv->total_buy_value;
            $classValues[$cls] = ($classValues[$cls] ?? 0) + $val;
            $riskNumerator    += $val * $risk;
            $totalValue       += $val;
        }

        // Liquidità conti — aggregazione unica per evitare N+1
        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->whereNotIn('type', ['broker'])
            ->where(fn($q) => $q->where('is_private', false)->orWhere('owner_user_id', $user->id))
            ->get();

        if ($accounts->isNotEmpty()) {
            $transactionSums = Transaction::whereIn('account_id', $accounts->pluck('id'))
                ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
                ->groupBy('account_id')
                ->pluck(DB::raw('SUM(amount)'), 'account_id');

            foreach ($accounts as $account) {
                $balance = (float) $account->initial_balance + (float) ($transactionSums[$account->id] ?? 0);
                if ($balance <= 0) {
                    continue;
                }

                $type = $account->type ?? 'other';
                $cls  = AssetClassificationService::ACCOUNT_TYPE_CLASS[$type] ?? 'liquidity';
                $risk = AssetClassificationService::ACCOUNT_TYPE_RISK[$type] ?? 1;
                $classValues[$cls] = ($classValues[$cls] ?? 0) + $balance;
                $riskNumerator    += $balance * $risk;
                $totalValue       += $balance;
            }
        }

        $allocation = [];
        foreach ($classValues as $cls => $val) {
            $allocation[] = [
                'asset_class' => $cls,
                'label'       => AssetClassificationService::CLASS_LABELS[$cls] ?? $cls,
                'color'       => AssetClassificationService::CLASS_COLORS[$cls] ?? '#94a3b8',
                'value'       => round($val, 2),
                'percentage'  => $totalValue > 0 ? round(($val / $totalValue) * 100, 1) : 0,
            ];
        }
        usort($allocation, fn($a, $b) => $b['value'] <=> $a['value']);

        $riskIndex = $totalValue > 0
            ? min(7, max(1, round($riskNumerator / $totalValue, 1)))
            : 1;

        return [
            'total_value' => round($totalValue, 2),
            'risk_index'  => $riskIndex,
            'risk_label'  => AssetClassificationService::getRiskLabel($riskIndex),
            'allocation'  => $allocation,
        ];
    }

    /**
     * Andamento mensile del patrimonio netto negli ultimi 12 mesi.
     * Usato dal widget "Patrimonio nel Tempo" della dashboard.
     */
    private function getNetWorthData(int $householdId, int $userId): array
    {
        $startDate = Carbon::now()->subYear()->startOfMonth();
        $endDate   = Carbon::now()->endOfDay();

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(fn($q) => $q->where('is_private', false)->orWhere('owner_user_id', $userId))
            ->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        $initialBalance = $accounts->sum(fn($a) => (float) $a->initial_balance);

        $balanceBeforePeriod = (float) Transaction::whereHas('account', fn($q) => $q->where('household_id', $householdId))
            ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->where('date', '<', $startDate)
            ->sum('amount');

        $runningBalance = $initialBalance + $balanceBeforePeriod;

        $isSqlite = DB::getDriverName() === 'sqlite';
        $yearExpr  = $isSqlite ? "CAST(strftime('%Y', date) AS INTEGER)" : 'YEAR(date)';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', date) AS INTEGER)" : 'MONTH(date)';

        $monthlyTransactions = Transaction::whereHas('account', fn($q) => $q->where('household_id', $householdId))
            ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw("{$yearExpr} as year, {$monthExpr} as month, SUM(amount) as net")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}")
            ->get()
            ->keyBy(fn($r) => "{$r->year}-{$r->month}");

        $result  = [];
        $current = $startDate->copy()->startOfMonth();

        while ($current->lte($endDate)) {
            $key = $current->year . '-' . $current->month;
            if (isset($monthlyTransactions[$key])) {
                $runningBalance += (float) $monthlyTransactions[$key]->net;
            }
            $result[] = [
                'month'            => $current->translatedFormat('M Y'),
                'Patrimonio Netto' => round($runningBalance, 2),
            ];
            $current->addMonth();
        }

        return $result;
    }

    /**
     * Cashflow mensile (entrate / uscite / risparmio) degli ultimi 12 mesi.
     * Usato dal widget "Panoramica Cashflow" della dashboard.
     */
    private function getCashFlowData(int $householdId, int $userId): array
    {
        $startDate = Carbon::now()->subYear()->startOfMonth();
        $endDate   = Carbon::now()->endOfDay();

        $isSqlite  = DB::getDriverName() === 'sqlite';
        $yearExpr  = $isSqlite ? "CAST(strftime('%Y', date) AS INTEGER)" : 'YEAR(date)';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', date) AS INTEGER)" : 'MONTH(date)';

        $transactions = Transaction::whereHas('account', fn($q) => $q->where('household_id', $householdId))
            ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw("{$yearExpr} as year, {$monthExpr} as month, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income, SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}")
            ->get();

        return $transactions->map(fn($row) => [
            'month'     => Carbon::createFromDate($row->year, $row->month, 1)->translatedFormat('M Y'),
            'Entrate'   => round((float) $row->income, 2),
            'Uscite'    => round((float) $row->expenses, 2),
            'Risparmio' => round((float) $row->income - (float) $row->expenses, 2),
        ])->values()->toArray();
    }

    /**
     * Calcola le spese per categoria nel mese corrente.
     */
    private function getExpenseCategoryData(int $householdId, int $userId): array
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate   = Carbon::now()->endOfDay();

        $expenses = Transaction::with('category')
            ->whereHas('account', fn($q) => $q->where('household_id', $householdId))
            ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
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
                'name'        => $category?->name ?? 'Senza categoria',
                'value'       => round((float) $row->total, 2),
                'percentage'  => $grandTotal > 0 ? round(((float) $row->total / (float) $grandTotal) * 100, 1) : 0,
                'color'       => $category?->color ?? '#94a3b8',
                'icon'        => $category?->icon ?? '📁',
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
            ->map(fn($goal) => [
                'id'             => $goal->id,
                'name'           => $goal->name,
                'icon'           => $goal->icon,
                'color'          => $goal->color,
                'target_amount'  => (float) $goal->target_amount,
                'current_amount' => (float) $goal->current_amount,
                'currency_code'  => $goal->currency_code,
                'target_date'    => $goal->target_date?->format('Y-m-d'),
                'percentage'     => $goal->target_amount > 0
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
    private function getExpenseDistributionData(\App\Models\User $user, int $householdId): array
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate   = Carbon::now()->endOfDay();

        // Recupera soglie personalizzate da profile_settings (default 50/30/20)
        $settings   = $user->profile_settings ?? [];
        $thresholds = $settings['expense_distribution_thresholds'] ?? [
            'needs'       => 50,
            'wants'       => 30,
            'investments' => 20,
        ];

        // Aggrega le spese per categoria nel mese corrente
        $expenses = \App\Models\Transaction::with('category')
            ->whereHas('account', fn($q) => $q->where('household_id', $householdId))
            ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->whereBetween('date', [$startDate, $endDate])
            ->where('amount', '<', 0)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, SUM(ABS(amount)) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        $totalExpenses = (float) $expenses->sum('total');

        // Bucket iniziali
        $buckets = [
            'needs'       => ['amount' => 0.0, 'categories' => []],
            'wants'       => ['amount' => 0.0, 'categories' => []],
            'investments' => ['amount' => 0.0, 'categories' => []],
            'unclassified' => ['amount' => 0.0, 'categories' => []],
        ];

        foreach ($expenses as $row) {
            $category = $row->category;
            $amount   = (float) $row->total;
            $dist     = $category?->expense_distribution ?? null;
            $key      = in_array($dist, ['needs', 'wants', 'investments'], true) ? $dist : 'unclassified';

            $buckets[$key]['amount'] += $amount;
            $buckets[$key]['categories'][] = [
                'id'         => $category?->id,
                'name'       => $category?->name ?? 'Senza categoria',
                'icon'       => $category?->icon ?? '📁',
                'color'      => $category?->color ?? '#94a3b8',
                'amount'     => round($amount, 2),
                'percentage' => $totalExpenses > 0 ? round(($amount / $totalExpenses) * 100, 1) : 0,
            ];
        }

        // Costruisce il risultato finale con percentuali e flag di superamento soglia
        $result = [];
        foreach (['needs', 'wants', 'investments'] as $key) {
            $amount     = round($buckets[$key]['amount'], 2);
            $percentage = $totalExpenses > 0 ? round(($amount / $totalExpenses) * 100, 1) : 0;
            $threshold  = (float) ($thresholds[$key] ?? 0);

            $result[$key] = [
                'amount'     => $amount,
                'percentage' => $percentage,
                'threshold'  => $threshold,
                'exceeded'   => $threshold > 0 && $percentage > $threshold,
                'categories' => $buckets[$key]['categories'],
            ];
        }

        $unclassifiedAmount = round($buckets['unclassified']['amount'], 2);

        return [
            'needs'       => $result['needs'],
            'wants'       => $result['wants'],
            'investments' => $result['investments'],
            'unclassified' => [
                'amount'     => $unclassifiedAmount,
                'percentage' => $totalExpenses > 0 ? round(($unclassifiedAmount / $totalExpenses) * 100, 1) : 0,
                'categories' => $buckets['unclassified']['categories'],
            ],
            'total_expenses'      => round($totalExpenses, 2),
            'thresholds'          => [
                'needs'       => (float) ($thresholds['needs'] ?? 50),
                'wants'       => (float) ($thresholds['wants'] ?? 30),
                'investments' => (float) ($thresholds['investments'] ?? 20),
            ],
            'has_custom_thresholds' => isset($settings['expense_distribution_thresholds']),
            'current_month'         => Carbon::now()->translatedFormat('F Y'),
        ];
    }
}
