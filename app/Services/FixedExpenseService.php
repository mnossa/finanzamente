<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;

/**
 * Service per gestire il sistema di bilanciamento debiti e contributi spese fisse.
 */
class FixedExpenseService
{
    /**
     * Calcola i contributi dettagliati alle spese fisse per una household.
     */
    public function calculateFixedExpenseContributions(Household $household): array
    {
        if (! $household->isDebtBalancingMode()) {
            return [
                'error' => 'La household non utiliza il bilanciamento debiti',
                'contributions' => [],
            ];
        }

        $contributions = [];
        $users = $household->users()->get();

        // Inizializza i contributi per ogni utente
        foreach ($users as $user) {
            $contributions[$user->id] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'total_contributed' => 0,
                'expected_contribution' => 0,
                'balance' => 0, // Differenza tra contributo e atteso
                'categories' => [],
            ];
        }

        // Ottiene le categorie di spese fisse
        $fixedCategories = Category::where('household_id', $household->id)
            ->where('is_fixed_expense', true)
            ->get();

        if ($fixedCategories->isEmpty()) {
            return [
                'error' => null,
                'message' => 'Nessuna categoria di spese fisse configurata',
                'contributions' => $contributions,
            ];
        }

        $totalHouseholdContribution = 0;

        foreach ($fixedCategories as $category) {
            // Calcola il totale speso per questa categoria da tutti
            $categoryTotal = Transaction::whereIn('account_id',
                $household->accounts()->pluck('id')
            )
                ->where('category_id', $category->id)
                ->where('amount', '<', 0) // Solo spese
                ->operationalStats()
                ->sum('amount');

            $categoryTotal = abs($categoryTotal);
            $totalHouseholdContribution += $categoryTotal;

            foreach ($users as $user) {
                // Contributo effettivo dell'utente per questa categoria
                $userContribution = Transaction::whereIn('account_id',
                    $household->accounts()->where('owner_user_id', $user->id)->pluck('id')
                )
                    ->where('category_id', $category->id)
                    ->where('amount', '<', 0) // Solo spese
                    ->operationalStats()
                    ->sum('amount');

                $userContribution = abs($userContribution);

                // Calcola la percentuale di contributo dell'utente
                $contributionPercentage = $categoryTotal > 0 ?
                    ($userContribution / $categoryTotal) * 100 : 0;

                // Ottiene la percentuale prevista per questo utente
                $expectedPercentage = $household->getUserBalance($user->id);
                $expectedContribution = ($categoryTotal * $expectedPercentage) / 100;

                $contributions[$user->id]['categories'][$category->id] = [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'user_contributed' => $userContribution,
                    'category_total' => $categoryTotal,
                    'contribution_percentage' => round($contributionPercentage, 2),
                    'expected_percentage' => $expectedPercentage,
                    'expected_contribution' => $expectedContribution,
                    'category_balance' => $userContribution - $expectedContribution,
                ];

                $contributions[$user->id]['total_contributed'] += $userContribution;
                $contributions[$user->id]['expected_contribution'] += $expectedContribution;
            }
        }

        // Calcola il bilancio finale per ogni utente
        foreach ($contributions as &$userContribution) {
            $userContribution['balance'] =
                $userContribution['total_contributed'] - $userContribution['expected_contribution'];
        }

        return [
            'error' => null,
            'message' => null,
            'total_household_expenses' => $totalHouseholdContribution,
            'fixed_categories_count' => $fixedCategories->count(),
            'contributions' => $contributions,
        ];
    }

    /**
     * Suggerisce il prossimo turno per una categoria di spese fisse.
     */
    public function suggestNextTurnForCategory(Household $household, int $categoryId): array
    {
        if (! $household->isTurnSuggestionsEnabled()) {
            return [
                'error' => 'Il suggeritore di turni non è abilitato per questa household',
                'suggestion' => null,
            ];
        }

        $category = Category::where('id', $categoryId)
            ->where('household_id', $household->id)
            ->where('is_fixed_expense', true)
            ->first();

        if (! $category) {
            return [
                'error' => 'Categoria non trovata o non è una spesa fissa',
                'suggestion' => null,
            ];
        }

        $suggestedUserId = $household->suggestNextTurn($categoryId);

        if (! $suggestedUserId) {
            return [
                'error' => 'Impossibile suggerire un turno',
                'suggestion' => null,
            ];
        }

        $suggestedUser = User::find($suggestedUserId);

        return [
            'error' => null,
            'suggestion' => [
                'user_id' => $suggestedUserId,
                'user_name' => $suggestedUser->name,
                'category_id' => $categoryId,
                'category_name' => $category->name,
                'last_user_id' => $household->getLastTurnAssignment($categoryId),
            ],
        ];
    }

    /**
     * Registra un turno completato per una categoria.
     */
    public function registerTurnCompleted(Household $household, int $categoryId, int $userId): bool
    {
        if (! $household->isTurnSuggestionsEnabled()) {
            return false;
        }

        $household->setLastTurnAssignment($categoryId, $userId);

        return true;
    }

    /**
     * Ottiene le statistiche per la dashboard delle spese fisse.
     */
    public function getDashboardStats(Household $household): array
    {
        $contributions = $this->calculateFixedExpenseContributions($household);

        if ($contributions['error']) {
            return $contributions;
        }

        $stats = [
            'total_fixed_expenses' => $contributions['total_household_expenses'] ?? 0,
            'categories_count' => $contributions['fixed_categories_count'] ?? 0,
            'members_count' => count($contributions['contributions']),
            'balanced_members' => 0,
            'members_summary' => [],
        ];

        foreach ($contributions['contributions'] as $userContrib) {
            $isBalanced = abs($userContrib['balance']) <= 10; // Tolleranza di €10

            if ($isBalanced) {
                $stats['balanced_members']++;
            }

            $stats['members_summary'][] = [
                'name' => $userContrib['user_name'],
                'balance' => $userContrib['balance'],
                'is_balanced' => $isBalanced,
                'status' => $userContrib['balance'] > 10 ? 'creditor' :
                           ($userContrib['balance'] < -10 ? 'debtor' : 'balanced'),
            ];
        }

        return array_merge($contributions, ['stats' => $stats]);
    }
}
