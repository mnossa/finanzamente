<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ChartsController extends Controller
{
    /** Data di inizio minima per il periodo "Tutto" */
    private const MIN_HISTORY_DATE = '2000-01-01';
    /**
     * Mostra la pagina dei grafici interattivi.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;
        $period = $request->input('period', '1y');

        [$startDate, $endDate] = $this->getPeriodDates($period);

        return Inertia::render('Charts/Index', [
            'cashFlowData'       => $this->getCashFlowData($householdId, $user->id, $startDate, $endDate, $period),
            'expenseCategories'  => $this->getExpenseCategoryData($householdId, $user->id, $startDate, $endDate),
            'netWorthData'       => $this->getNetWorthData($householdId, $user->id, $startDate, $endDate, $period),
            'activeBudgets'      => $this->getActiveBudgetsData($householdId),
            'portfolioData'      => $this->getPortfolioData($householdId, $user->id),
            'period'             => $period,
        ]);
    }

    /**
     * Restituisce le date di inizio e fine per il periodo selezionato.
     */
    private function getPeriodDates(string $period): array
    {
        $endDate = Carbon::now()->endOfDay();

        $startDate = match ($period) {
            '7d'  => Carbon::now()->subDays(7)->startOfDay(),
            '30d' => Carbon::now()->subDays(30)->startOfDay(),
            '1y'  => Carbon::now()->subYear()->startOfMonth(),
            'max' => Carbon::parse(self::MIN_HISTORY_DATE)->startOfDay(),
            default => Carbon::now()->subYear()->startOfMonth(),
        };

        return [$startDate, $endDate];
    }

    /**
     * Calcola i dati di Cash Flow (entrate vs uscite) raggruppati per mese.
     */
    private function getCashFlowData(int $householdId, int $userId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        $transactions = Transaction::whereHas('account', fn($q) => $q->where('household_id', $householdId))
            ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('YEAR(date) as year, MONTH(date) as month, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income, SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses')
            ->groupByRaw('YEAR(date), MONTH(date)')
            ->orderByRaw('YEAR(date), MONTH(date)')
            ->get();

        return $transactions->map(fn($row) => [
            'month'    => Carbon::createFromDate($row->year, $row->month, 1)->translatedFormat('M Y'),
            'Entrate'  => round((float) $row->income, 2),
            'Uscite'   => round((float) $row->expenses, 2),
            'Risparmio' => round((float) $row->income - (float) $row->expenses, 2),
        ])->values()->toArray();
    }

    /**
     * Calcola le spese per categoria nel periodo selezionato.
     */
    private function getExpenseCategoryData(int $householdId, int $userId, Carbon $startDate, Carbon $endDate): array
    {
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
                'name'       => $category?->name ?? 'Senza categoria',
                'value'      => round((float) $row->total, 2),
                'percentage' => $grandTotal > 0 ? round(((float) $row->total / (float) $grandTotal) * 100, 1) : 0,
                'color'      => $category?->color ?? '#94a3b8',
                'icon'       => $category?->icon ?? '📁',
                'category_id' => $row->category_id,
            ];
        })->values()->toArray();
    }

    /**
     * Calcola l'andamento del patrimonio netto nel tempo.
     */
    private function getNetWorthData(int $householdId, int $userId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        // Ottieni tutti i conti attivi
        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(fn($q) => $q->where('is_private', false)->orWhere('owner_user_id', $userId))
            ->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        // Calcola il saldo iniziale complessivo dei conti (prima del periodo)
        $initialBalance = $accounts->sum(fn($account) => (float) $account->initial_balance);

        // Recupera tutte le transazioni prima del periodo
        $balanceBeforePeriod = (float) Transaction::whereHas('account', fn($q) => $q->where('household_id', $householdId))
            ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->where('date', '<', $startDate)
            ->sum('amount');

        $runningBalance = $initialBalance + $balanceBeforePeriod;

        // Raggruppa transazioni per mese nel periodo
        $monthlyTransactions = Transaction::whereHas('account', fn($q) => $q->where('household_id', $householdId))
            ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('YEAR(date) as year, MONTH(date) as month, SUM(amount) as net')
            ->groupByRaw('YEAR(date), MONTH(date)')
            ->orderByRaw('YEAR(date), MONTH(date)')
            ->get()
            ->keyBy(fn($r) => "{$r->year}-{$r->month}");

        // Genera l'array dei mesi nel periodo
        $result = [];
        $current = $startDate->copy()->startOfMonth();

        while ($current->lte($endDate)) {
            $key = $current->year . '-' . $current->month;
            if (isset($monthlyTransactions[$key])) {
                $runningBalance += (float) $monthlyTransactions[$key]->net;
            }
            $result[] = [
                'month'         => $current->translatedFormat('M Y'),
                'Patrimonio Netto' => round($runningBalance, 2),
            ];
            $current->addMonth();
        }

        return $result;
    }

    /**
     * Recupera i budget attivi con stato di avanzamento.
     */
    private function getActiveBudgetsData(int $householdId): array
    {
        return Budget::where('household_id', $householdId)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->with(['category', 'currency'])
            ->get()
            ->map(function ($budget) use ($householdId) {
                $spent = (float) Transaction::whereHas('account', fn($q) => $q->where('household_id', $householdId))
                    ->where('category_id', $budget->category_id)
                    ->whereBetween('date', [$budget->period_start, $budget->period_end])
                    ->where('amount', '<', 0)
                    ->sum(DB::raw('ABS(amount)'));

                $percentage = $budget->amount > 0
                    ? round(($spent / $budget->amount) * 100, 1)
                    : 0;

                return [
                    'id'              => $budget->id,
                    'category_name'   => $budget->category->name,
                    'category_icon'   => $budget->category->icon,
                    'category_color'  => $budget->category->color,
                    'amount'          => (float) $budget->amount,
                    'spent'           => $spent,
                    'percentage'      => $percentage,
                    'is_exceeded'     => $spent > $budget->amount,
                    'currency_symbol' => $budget->currency->symbol,
                ];
            })->values()->toArray();
    }

    /**
     * Calcola l'allocazione del portafoglio investimenti per tipologia di asset.
     */
    private function getPortfolioData(int $householdId, int $userId): array
    {
        $investments = Investment::with('asset')
            ->where('household_id', $householdId)
            ->where(fn($q) => $q->where('is_private', false)->orWhere('user_id', $userId))
            ->whereNull('sell_date')
            ->get();

        if ($investments->isEmpty()) {
            return [];
        }

        $byType = $investments->groupBy(fn($inv) => $inv->asset?->type ?? array_key_last(InvestmentAsset::TYPES));

        $typeLabels = InvestmentAsset::TYPES;

        $totalValue = $investments->sum(fn($inv) => (float) $inv->quantity * (float) $inv->buy_price);

        return $byType->map(function ($group, $type) use ($typeLabels, $totalValue) {
            $value = $group->sum(fn($inv) => (float) $inv->quantity * (float) $inv->buy_price);
            return [
                'name'       => $typeLabels[$type] ?? ucfirst($type),
                'value'      => round($value, 2),
                'percentage' => $totalValue > 0 ? round(($value / $totalValue) * 100, 1) : 0,
            ];
        })->values()->toArray();
    }
}
