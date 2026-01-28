<?php

namespace App\Http\Controllers;

use App\Services\FixedExpenseService;
use App\Models\Household;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FixedExpenseController extends Controller
{
    protected $fixedExpenseService;

    public function __construct(FixedExpenseService $fixedExpenseService)
    {
        $this->fixedExpenseService = $fixedExpenseService;
    }

    /**
     * Mostra la dashboard delle spese fisse per una household.
     */
    public function dashboard(Household $household)
    {
        // Verifica che l'utente appartenga alla household
        abort_unless(
            $household->users()->where('user_id', auth()->id())->exists(), 
            403
        );

        if (!$household->isDebtBalancingMode()) {
            return redirect()->route('households.show', $household)
                ->with('error', 'La dashboard spese fisse è disponibile solo per household con bilanciamento debiti.');
        }

        $dashboardData = $this->fixedExpenseService->getDashboardStats($household);
        
        $fixedCategories = Category::where('household_id', $household->id)
            ->where('is_fixed_expense', true)
            ->get();

        return Inertia::render('FixedExpenses/Dashboard', [
            'household' => $household->load('users'),
            'dashboardData' => $dashboardData,
            'fixedCategories' => $fixedCategories,
            'turnSuggestionsEnabled' => $household->isTurnSuggestionsEnabled(),
        ]);
    }

    /**
     * Ottiene i contributi dettagliati per una household.
     */
    public function getContributions(Household $household)
    {
        abort_unless(
            $household->users()->where('user_id', auth()->id())->exists(), 
            403
        );

        $contributions = $this->fixedExpenseService->calculateFixedExpenseContributions($household);
        
        return response()->json($contributions);
    }

    /**
     * Suggerisce il prossimo turno per una categoria.
     */
    public function suggestTurn(Household $household, Category $category)
    {
        abort_unless(
            $household->users()->where('user_id', auth()->id())->exists(), 
            403
        );

        abort_unless($category->household_id === $household->id, 404);

        $suggestion = $this->fixedExpenseService->suggestNextTurnForCategory(
            $household, 
            $category->id
        );
        
        return response()->json($suggestion);
    }

    /**
     * Registra un turno completato.
     */
    public function completeTurn(Request $request, Household $household, Category $category)
    {
        abort_unless(
            $household->users()->where('user_id', auth()->id())->exists(), 
            403
        );

        abort_unless($category->household_id === $household->id, 404);

        $request->validate([
            'user_id' => 'required|integer|exists:users,id'
        ]);

        // Verifica che l'utente sia membro della household
        abort_unless(
            $household->users()->where('user_id', $request->user_id)->exists(),
            422,
            'L\'utente selezionato non appartiene a questa household.'
        );

        $success = $this->fixedExpenseService->registerTurnCompleted(
            $household, 
            $category->id, 
            $request->user_id
        );

        if (!$success) {
            return response()->json([
                'error' => 'Impossibile registrare il turno. Verifica che il suggeritore sia abilitato.'
            ], 422);
        }

        return response()->json([
            'message' => 'Turno registrato con successo',
            'success' => true
        ]);
    }

    /**
     * Aggiorna le impostazioni del suggeritore di turni.
     */
    public function updateTurnSettings(Request $request, Household $household)
    {
        // Solo il proprietario può modificare le impostazioni
        abort_unless($household->owner_user_id === auth()->id(), 403);

        $request->validate([
            'enable_turn_suggestions' => 'required|boolean',
            'turn_suggestion_settings' => 'sometimes|array',
        ]);

        $household->update([
            'enable_turn_suggestions' => $request->enable_turn_suggestions,
            'turn_suggestion_settings' => $request->turn_suggestion_settings ?? []
        ]);

        return redirect()->back()->with('success', 'Impostazioni suggeritore aggiornate con successo.');
    }
}
