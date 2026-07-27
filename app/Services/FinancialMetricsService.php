<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

/**
 * Service centralizzato per la logica di calcolo del Lifestyle Inflation Score.
 *
 * RedditoNetto = RedditoLordo (nessuna stima fiscale P.IVA).
 *
 * Spese:
 *   SpeseEffettive = TotaleUscite − EsclusiDalScore  (cat. escluse; transfer/investment esclusi a query)
 *   LifestyleScore = (RedditoNetto − SpeseEffettive) / RedditoNetto × 100
 */
class FinancialMetricsService
{
    /**
     * Calcola tutti i dati necessari al widget/pagina Lifestyle Score.
     *
     * @return array{
     *   gross_income: float,
     *   estimated_taxes: float,
     *   inps_amount: float,
     *   flat_tax_amount: float,
     *   net_income: float,
     *   total_expenses: float,
     *   excluded_expenses: float,
     *   effective_expenses: float,
     *   lifestyle_score: float|null,
     *   tax_rate: float,
     *   inps_rate: float,
     *   is_partita_iva: bool,
     *   category_breakdown: array
     * }
     */
    public function calculate(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $householdId = $user->active_household_id;

        // ── Reddito Lordo ────────────────────────────────────────────────────────
        // Somma entrate (amount > 0) esclusi trasferimenti, investimenti e IH marcati
        $grossIncome = (float) Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->where('amount', '>', 0)
            ->operationalStats()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');

        $netIncome = $grossIncome;

        // ── Totale Uscite (spese lorde) ──────────────────────────────────────────
        $totalExpenses = (float) abs(
            Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
                ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
                ->where('amount', '<', 0)
                ->operationalStats()
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount')
        );

        // ── Spese escluse dal calcolo ────────────────────────────────────────────
        $excludedExpenses = (float) abs(
            Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
                ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
                ->where('amount', '<', 0)
                ->operationalStats()
                ->whereHas('category', fn ($q) => $q->where('exclude_from_lifestyle_score', true))
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount')
        );

        $effectiveExpenses = max(0.0, $totalExpenses - $excludedExpenses);

        // ── Lifestyle Score (percentuale) ────────────────────────────────────────
        $lifestyleScore = $netIncome > 0
            ? round((($netIncome - $effectiveExpenses) / $netIncome) * 100, 1)
            : null;

        // ── Breakdown per categoria ──────────────────────────────────────────────
        $categoryBreakdown = $this->buildCategoryBreakdown($user, $householdId, $startDate, $endDate, $totalExpenses);

        return [
            'gross_income' => round($grossIncome, 2),
            'estimated_taxes' => 0.0,
            'inps_amount' => 0.0,
            'flat_tax_amount' => 0.0,
            'net_income' => round($netIncome, 2),
            'total_expenses' => round($totalExpenses, 2),
            'excluded_expenses' => round($excludedExpenses, 2),
            'effective_expenses' => round($effectiveExpenses, 2),
            'lifestyle_score' => $lifestyleScore,
            'tax_rate' => 0.0,
            'inps_rate' => 0.0,
            'is_partita_iva' => false,
            'category_breakdown' => $categoryBreakdown,
        ];
    }

    /**
     * Costruisce il breakdown delle spese per categoria.
     *
     * @return array<int, array{
     *   category_id: int|null,
     *   name: string,
     *   icon: string|null,
     *   color: string|null,
     *   amount: float,
     *   percentage: float,
     *   excluded: bool
     * }>
     */
    private function buildCategoryBreakdown(
        User $user,
        int $householdId,
        Carbon $startDate,
        Carbon $endDate,
        float $totalExpenses
    ): array {
        $rows = Transaction::selectRaw('category_id, SUM(amount) as total')
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->where('amount', '<', 0)
            ->operationalStats()
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('category_id')
            ->with('category:id,name,icon,color,exclude_from_lifestyle_score')
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            $amount = abs((float) $row->total);
            $pct = $totalExpenses > 0 ? round(($amount / $totalExpenses) * 100, 1) : 0.0;

            $breakdown[] = [
                'category_id' => $row->category_id,
                'name' => $row->category?->name ?? 'Senza categoria',
                'icon' => $row->category?->icon,
                'color' => $row->category?->color,
                'amount' => round($amount, 2),
                'percentage' => $pct,
                'excluded' => (bool) ($row->category?->exclude_from_lifestyle_score ?? false),
            ];
        }

        // Ordina per importo decrescente
        usort($breakdown, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return $breakdown;
    }
}
