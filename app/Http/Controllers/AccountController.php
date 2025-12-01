<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Mostra l'elenco dei conti della household attiva.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $accounts = Account::where('household_id', $householdId)
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->with('owner:id,name')
            ->orderBy('name')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'type_label' => Account::TYPES[$account->type] ?? $account->type,
                    'initial_balance' => (float) $account->initial_balance,
                    'current_balance' => (float) $account->current_balance,
                    'currency_code' => $account->currency_code,
                    'active' => $account->active,
                    'is_private' => $account->is_private,
                    'owner' => $account->owner ? [
                        'id' => $account->owner->id,
                        'name' => $account->owner->name,
                    ] : null,
                    'created_at' => $account->created_at->format('Y-m-d'),
                ];
            });

        // Calcola totali per tipo
        $totalsByType = $accounts->groupBy('type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total' => $group->sum('current_balance'),
            ];
        });

        $totalBalance = $accounts->sum('current_balance');

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'totalsByType' => $totalsByType,
            'totalBalance' => $totalBalance,
            'accountTypes' => Account::TYPES,
        ]);
    }

    /**
     * Mostra il form per creare un nuovo conto.
     */
    public function create(): Response
    {
        $currencies = Currency::orderBy('code')->get(['code', 'name', 'symbol']);

        return Inertia::render('Accounts/Create', [
            'accountTypes' => Account::TYPES,
            'currencies' => $currencies,
            'defaultCurrency' => 'EUR',
        ]);
    }

    /**
     * Salva un nuovo conto.
     */
    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $account = new Account($validated);
        $account->household_id = $user->active_household_id;
        $account->current_balance = $validated['initial_balance'];

        // Se il conto è privato, assegna l'owner
        if ($validated['is_private'] ?? false) {
            $account->owner_user_id = $user->id;
        }

        $account->save();

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Conto creato con successo.');
    }

    /**
     * Mostra i dettagli di un conto.
     */
    public function show(Account $account): Response
    {
        $this->authorizeAccount($account);

        // Carica le ultime transazioni del conto
        $recentTransactions = $account->transactions()
            ->with(['category:id,name,color,icon', 'user:id,name'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'amount' => (float) $transaction->amount,
                    'date' => $transaction->date->format('Y-m-d'),
                    'description' => $transaction->description,
                    'category' => $transaction->category,
                    'user' => $transaction->user,
                ];
            });

        return Inertia::render('Accounts/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => Account::TYPES[$account->type] ?? $account->type,
                'initial_balance' => (float) $account->initial_balance,
                'current_balance' => (float) $account->current_balance,
                'currency_code' => $account->currency_code,
                'active' => $account->active,
                'is_private' => $account->is_private,
                'created_at' => $account->created_at->format('d/m/Y'),
            ],
            'recentTransactions' => $recentTransactions,
        ]);
    }

    /**
     * Mostra il form per modificare un conto.
     */
    public function edit(Account $account): Response
    {
        $this->authorizeAccount($account);

        $currencies = Currency::orderBy('code')->get(['code', 'name', 'symbol']);

        return Inertia::render('Accounts/Edit', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'initial_balance' => (float) $account->initial_balance,
                'currency_code' => $account->currency_code,
                'active' => $account->active,
                'is_private' => $account->is_private,
            ],
            'accountTypes' => Account::TYPES,
            'currencies' => $currencies,
        ]);
    }

    /**
     * Aggiorna un conto esistente.
     */
    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorizeAccount($account);

        $validated = $request->validated();
        $user = Auth::user();

        // Se cambia il saldo iniziale, ricalcola il saldo corrente
        if (isset($validated['initial_balance']) && $validated['initial_balance'] != $account->initial_balance) {
            $difference = $validated['initial_balance'] - $account->initial_balance;
            $validated['current_balance'] = $account->current_balance + $difference;
        }

        // Gestione owner per conto privato
        if (($validated['is_private'] ?? false) && !$account->is_private) {
            $validated['owner_user_id'] = $user->id;
        } elseif (!($validated['is_private'] ?? true) && $account->is_private) {
            $validated['owner_user_id'] = null;
        }

        $account->update($validated);

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Conto aggiornato con successo.');
    }

    /**
     * Elimina un conto (soft delete).
     */
    public function destroy(Account $account): RedirectResponse
    {
        $this->authorizeAccount($account);

        // Verifica che non ci siano transazioni
        if ($account->transactions()->exists()) {
            return back()->with('error', 'Non puoi eliminare un conto con transazioni. Archivialo invece.');
        }

        $account->delete();

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Conto eliminato con successo.');
    }

    /**
     * Archivia/Riattiva un conto.
     */
    public function toggleActive(Account $account): RedirectResponse
    {
        $this->authorizeAccount($account);

        $account->active = !$account->active;
        $account->save();

        $message = $account->active
            ? 'Conto riattivato con successo.'
            : 'Conto archiviato con successo.';

        return back()->with('success', $message);
    }

    /**
     * Verifica che l'utente possa accedere al conto.
     */
    private function authorizeAccount(Account $account): void
    {
        $user = Auth::user();

        // Deve appartenere alla household attiva
        if ($account->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questo conto.');
        }

        // Se è privato, deve essere il proprietario
        if ($account->is_private && $account->owner_user_id !== $user->id) {
            abort(403, 'Questo conto è privato.');
        }
    }
}
