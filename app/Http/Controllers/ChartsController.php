<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

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
            'expenseCategories' => $this->getExpenseCategoryData($householdId, $user->id, $startDate, $endDate),
            'activeBudgets' => $this->getActiveBudgetsData($householdId),
            'period' => $period,
        ]);
    }

    /**
     * Restituisce le date di inizio e fine per il periodo selezionato.
     */
    private function getPeriodDates(string $period): array
    {
        $endDate = Carbon::now()->endOfDay();

        $startDate = match ($period) {
            '7d' => Carbon::now()->subDays(7)->startOfDay(),
            '30d' => Carbon::now()->subDays(30)->startOfDay(),
            '1y' => Carbon::now()->subYear()->startOfMonth(),
            'max' => Carbon::parse(self::MIN_HISTORY_DATE)->startOfDay(),
            default => Carbon::now()->subYear()->startOfMonth(),
        };

        return [$startDate, $endDate];
    }

    /**
     * Calcola le spese per categoria nel periodo selezionato.
     */
    private function getExpenseCategoryData(int $householdId, int $userId, Carbon $startDate, Carbon $endDate): array
    {
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
                $spent = (float) Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
                    ->where('category_id', $budget->category_id)
                    ->whereBetween('date', [$budget->period_start, $budget->period_end])
                    ->where('amount', '<', 0)
                    ->sum(DB::raw('ABS(amount)'));

                $percentage = $budget->amount > 0
                    ? round(($spent / $budget->amount) * 100, 1)
                    : 0;

                return [
                    'id' => $budget->id,
                    'category_name' => $budget->category->name,
                    'category_icon' => $budget->category->icon,
                    'category_color' => $budget->category->color,
                    'amount' => (float) $budget->amount,
                    'spent' => $spent,
                    'percentage' => $percentage,
                    'is_exceeded' => $spent > $budget->amount,
                    'currency_symbol' => $budget->currency->symbol,
                ];
            })->values()->toArray();
    }
}
