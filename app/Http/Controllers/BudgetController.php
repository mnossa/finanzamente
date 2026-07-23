<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    /**
     * Mostra l'elenco dei budget della household attiva.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $budgets = Budget::where('household_id', $householdId)
            ->with(['category', 'currency'])
            ->orderBy('period_start', 'desc')
            ->get()
            ->map(function ($budget) use ($householdId) {
                // Calcola la spesa effettiva per questo budget
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
                    'category' => [
                        'id' => $budget->category->id,
                        'name' => $budget->category->name,
                        'icon' => $budget->category->icon,
                    ],
                    'amount' => $budget->amount,
                    'spent' => $spent,
                    'remaining' => max(0, $budget->amount - $spent),
                    'percentage' => $percentage,
                    'currency' => [
                        'code' => $budget->currency->code,
                        'symbol' => $budget->currency->symbol,
                    ],
                    'period_start' => $budget->period_start->format('Y-m-d'),
                    'period_end' => $budget->period_end->format('Y-m-d'),
                    'description' => $budget->description,
                    'is_exceeded' => $spent > $budget->amount,
                    'is_active' => $budget->period_start <= now() && $budget->period_end >= now(),
                ];
            });

        $monthlyIncome = $this->monthlyIncomeForHousehold($householdId, $user);

        return Inertia::render('Budgets/Index', [
            'budgets' => $budgets,
            'monthlyIncome' => $monthlyIncome,
        ]);
    }

    /**
     * Mostra il form per creare un nuovo budget.
     */
    public function create(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        // Solo categorie di spesa
        $categories = Category::where('household_id', $householdId)
            ->where('type', 'expense')
            ->orderBy('name')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'icon' => $cat->icon,
            ]);

        $currencies = Currency::orderBy('code')
            ->get()
            ->map(fn ($c) => [
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
            ]);

        return Inertia::render('Budgets/Create', [
            'categories' => $categories,
            'currencies' => $currencies,
        ]);
    }

    /**
     * Salva un nuovo budget.
     */
    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        Budget::create([
            'household_id' => $user->active_household_id,
            'category_id' => $validated['category_id'],
            'amount' => $validated['amount'],
            'currency_code' => $validated['currency_code'],
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Budget creato con successo.');
    }

    /**
     * Mostra i dettagli di un budget.
     */
    public function show(Budget $budget): Response
    {
        $this->authorizeBudget($budget);

        $householdId = Auth::user()->active_household_id;

        // Transazioni associate a questo budget
        $transactions = Transaction::whereHas('account', function ($query) use ($householdId) {
            $query->where('household_id', $householdId);
        })
            ->where('category_id', $budget->category_id)
            ->whereHas('category', function ($query) {
                $query->where('type', 'expense');
            })
            ->whereBetween('date', [$budget->period_start, $budget->period_end])
            ->with(['account'])
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'description' => $t->description,
                'amount' => $t->amount,
                'date' => $t->date->format('Y-m-d'),
                'account' => $t->account->name,
            ]);

        $spent = $transactions->sum('amount');
        $percentage = $budget->amount > 0
            ? min(100, round(($spent / $budget->amount) * 100, 1))
            : 0;

        return Inertia::render('Budgets/Show', [
            'budget' => [
                'id' => $budget->id,
                'category' => [
                    'id' => $budget->category->id,
                    'name' => $budget->category->name,
                    'icon' => $budget->category->icon,
                ],
                'amount' => $budget->amount,
                'spent' => $spent,
                'remaining' => max(0, $budget->amount - $spent),
                'percentage' => $percentage,
                'currency' => [
                    'code' => $budget->currency->code,
                    'symbol' => $budget->currency->symbol,
                ],
                'period_start' => $budget->period_start->format('Y-m-d'),
                'period_end' => $budget->period_end->format('Y-m-d'),
                'description' => $budget->description,
                'is_exceeded' => $spent > $budget->amount,
            ],
            'transactions' => $transactions,
        ]);
    }

    /**
     * Mostra il form per modificare un budget.
     */
    public function edit(Budget $budget): Response
    {
        $this->authorizeBudget($budget);

        $user = Auth::user();
        $householdId = $user->active_household_id;

        $categories = Category::where('household_id', $householdId)
            ->where('type', 'expense')
            ->orderBy('name')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'icon' => $cat->icon,
            ]);

        $currencies = Currency::orderBy('code')
            ->get()
            ->map(fn ($c) => [
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
            ]);

        return Inertia::render('Budgets/Edit', [
            'budget' => [
                'id' => $budget->id,
                'category_id' => $budget->category_id,
                'amount' => $budget->amount,
                'currency_code' => $budget->currency_code,
                'period_start' => $budget->period_start->format('Y-m-d'),
                'period_end' => $budget->period_end->format('Y-m-d'),
                'description' => $budget->description,
            ],
            'categories' => $categories,
            'currencies' => $currencies,
        ]);
    }

    /**
     * Aggiorna un budget esistente.
     */
    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        $this->authorizeBudget($budget);

        $budget->update($request->validated());

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Budget aggiornato con successo.');
    }

    /**
     * Elimina un budget.
     */
    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorizeBudget($budget);

        $budget->delete();

        return redirect()
            ->route('budgets.index')
            ->with('success', 'Budget eliminato con successo.');
    }

    /**
     * Entrate reali del mese corrente (amount > 0, no trasferimenti).
     */
    private function monthlyIncomeForHousehold(?int $householdId, User $user): float
    {
        if ($householdId === null) {
            return 0.0;
        }

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        return round((float) Transaction::whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->where('amount', '>', 0)
            ->whereNull('transfer_id')
            ->excludeInterHouseholdStats()
            ->whereBetween('date', [$start, $end])
            ->sum('amount'), 2);
    }

    /**
     * Verifica che l'utente possa accedere al budget.
     */
    private function authorizeBudget(Budget $budget): void
    {
        $user = Auth::user();

        if ($budget->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questo budget.');
        }
    }
}
