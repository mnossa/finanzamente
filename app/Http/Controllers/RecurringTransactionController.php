<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringTransactionRequest;
use App\Http\Requests\UpdateRecurringTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTransactionController extends Controller
{
    /**
     * Frequenze disponibili per le transazioni ricorrenti.
     */
    public const FREQUENCIES = [
        'daily' => 'Giornaliera',
        'weekly' => 'Settimanale',
        'monthly' => 'Mensile',
        'yearly' => 'Annuale',
    ];

    /**
     * Mostra l'elenco delle transazioni ricorrenti della household attiva.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $recurringTransactions = RecurringTransaction::with([
                'account:id,name,currency_code',
                'category:id,name,color,icon,type',
                'user:id,name'
            ])
            ->whereHas('account', function ($q) use ($householdId) {
                $q->where('household_id', $householdId);
            })
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($rt) {
                $nextDue = $this->calculateNextDueDate($rt);
                $isActive = $this->isActive($rt);
                
                return [
                    'id' => $rt->id,
                    'amount' => (float) $rt->amount,
                    'frequency' => $rt->frequency,
                    'frequency_label' => self::FREQUENCIES[$rt->frequency] ?? $rt->frequency,
                    'start_date' => $rt->start_date->format('Y-m-d'),
                    'end_date' => $rt->end_date?->format('Y-m-d'),
                    'description' => $rt->description,
                    'next_due_date' => $nextDue?->format('Y-m-d'),
                    'is_active' => $isActive,
                    'category' => $rt->category ? [
                        'id' => $rt->category->id,
                        'name' => $rt->category->name,
                        'color' => $rt->category->color,
                        'icon' => $rt->category->icon,
                        'type' => $rt->category->type,
                    ] : null,
                    'account' => [
                        'id' => $rt->account->id,
                        'name' => $rt->account->name,
                        'currency_code' => $rt->account->currency_code,
                    ],
                    'user' => [
                        'id' => $rt->user->id,
                        'name' => $rt->user->name,
                    ],
                ];
            });

        return Inertia::render('RecurringTransactions/Index', [
            'recurringTransactions' => $recurringTransactions,
            'frequencies' => self::FREQUENCIES,
        ]);
    }

    /**
     * Mostra il form per creare una nuova transazione ricorrente.
     */
    public function create(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'currency_code']);

        $categories = Category::where(function ($q) use ($householdId) {
            $q->where('household_id', $householdId)
                ->orWhereNull('household_id');
        })
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'color', 'icon']);

        return Inertia::render('RecurringTransactions/Create', [
            'accounts' => $accounts,
            'categories' => $categories,
            'frequencies' => self::FREQUENCIES,
        ]);
    }

    /**
     * Salva una nuova transazione ricorrente.
     */
    public function store(StoreRecurringTransactionRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Determina il segno dell'importo in base al tipo di categoria
        $category = Category::find($validated['category_id']);
        $amount = abs($validated['amount']);
        if ($category && $category->type === 'expense') {
            $amount = -$amount;
        }

        $account = Account::find($validated['account_id']);

        RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $validated['account_id'],
            'category_id' => $validated['category_id'],
            'amount' => $amount,
            'currency_code' => $account->currency_code,
            'frequency' => $validated['frequency'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('recurring-transactions.index')
            ->with('success', 'Transazione ricorrente creata con successo.');
    }

    /**
     * Mostra i dettagli di una transazione ricorrente.
     */
    public function show(RecurringTransaction $recurringTransaction): Response
    {
        $this->authorizeRecurringTransaction($recurringTransaction);

        $recurringTransaction->load([
            'account:id,name,currency_code',
            'category:id,name,color,icon,type',
            'user:id,name'
        ]);

        $nextDue = $this->calculateNextDueDate($recurringTransaction);
        $isActive = $this->isActive($recurringTransaction);

        // Conta le transazioni generate da questa ricorrente
        $generatedCount = Transaction::where('recurring_transaction_id', $recurringTransaction->id)->count();

        return Inertia::render('RecurringTransactions/Show', [
            'recurringTransaction' => [
                'id' => $recurringTransaction->id,
                'amount' => (float) $recurringTransaction->amount,
                'frequency' => $recurringTransaction->frequency,
                'frequency_label' => self::FREQUENCIES[$recurringTransaction->frequency] ?? $recurringTransaction->frequency,
                'start_date' => $recurringTransaction->start_date->format('Y-m-d'),
                'end_date' => $recurringTransaction->end_date?->format('Y-m-d'),
                'description' => $recurringTransaction->description,
                'next_due_date' => $nextDue?->format('Y-m-d'),
                'is_active' => $isActive,
                'generated_count' => $generatedCount,
                'created_at' => $recurringTransaction->created_at->format('d/m/Y H:i'),
                'updated_at' => $recurringTransaction->updated_at->format('d/m/Y H:i'),
                'category' => $recurringTransaction->category,
                'account' => $recurringTransaction->account,
                'user' => $recurringTransaction->user,
            ],
            'frequencies' => self::FREQUENCIES,
        ]);
    }

    /**
     * Mostra il form per modificare una transazione ricorrente.
     */
    public function edit(RecurringTransaction $recurringTransaction): Response
    {
        $this->authorizeRecurringTransaction($recurringTransaction);

        $user = Auth::user();
        $householdId = $user->active_household_id;

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'currency_code']);

        $categories = Category::where(function ($q) use ($householdId) {
            $q->where('household_id', $householdId)
                ->orWhereNull('household_id');
        })
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'color', 'icon']);

        return Inertia::render('RecurringTransactions/Edit', [
            'recurringTransaction' => [
                'id' => $recurringTransaction->id,
                'account_id' => $recurringTransaction->account_id,
                'category_id' => $recurringTransaction->category_id,
                'amount' => abs((float) $recurringTransaction->amount),
                'frequency' => $recurringTransaction->frequency,
                'start_date' => $recurringTransaction->start_date->format('Y-m-d'),
                'end_date' => $recurringTransaction->end_date?->format('Y-m-d'),
                'description' => $recurringTransaction->description,
            ],
            'accounts' => $accounts,
            'categories' => $categories,
            'frequencies' => self::FREQUENCIES,
        ]);
    }

    /**
     * Aggiorna una transazione ricorrente esistente.
     */
    public function update(UpdateRecurringTransactionRequest $request, RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorizeRecurringTransaction($recurringTransaction);

        $validated = $request->validated();

        // Determina il segno dell'importo
        $category = Category::find($validated['category_id']);
        $amount = abs($validated['amount']);
        if ($category && $category->type === 'expense') {
            $amount = -$amount;
        }

        $recurringTransaction->update([
            'account_id' => $validated['account_id'],
            'category_id' => $validated['category_id'],
            'amount' => $amount,
            'frequency' => $validated['frequency'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('recurring-transactions.index')
            ->with('success', 'Transazione ricorrente aggiornata con successo.');
    }

    /**
     * Elimina una transazione ricorrente (soft delete).
     */
    public function destroy(RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorizeRecurringTransaction($recurringTransaction);

        $recurringTransaction->delete();

        return redirect()
            ->route('recurring-transactions.index')
            ->with('success', 'Transazione ricorrente eliminata con successo.');
    }

    /**
     * Genera manualmente la prossima transazione dalla ricorrente.
     */
    public function generate(RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorizeRecurringTransaction($recurringTransaction);

        if (!$this->isActive($recurringTransaction)) {
            return redirect()
                ->route('recurring-transactions.show', $recurringTransaction)
                ->with('error', 'Questa transazione ricorrente non è più attiva.');
        }

        $nextDue = $this->calculateNextDueDate($recurringTransaction);
        
        if (!$nextDue) {
            return redirect()
                ->route('recurring-transactions.show', $recurringTransaction)
                ->with('error', 'Nessuna data disponibile per generare la transazione.');
        }

        // Crea la transazione
        Transaction::create([
            'user_id' => $recurringTransaction->user_id,
            'account_id' => $recurringTransaction->account_id,
            'category_id' => $recurringTransaction->category_id,
            'amount' => $recurringTransaction->amount,
            'currency_code' => $recurringTransaction->currency_code,
            'date' => $nextDue,
            'description' => $recurringTransaction->description,
            'recurring' => true,
            'recurring_transaction_id' => $recurringTransaction->id,
            'is_private' => false,
        ]);

        // Aggiorna il saldo del conto
        $account = $recurringTransaction->account;
        $account->current_balance += (float) $recurringTransaction->amount;
        $account->save();

        return redirect()
            ->route('recurring-transactions.show', $recurringTransaction)
            ->with('success', 'Transazione generata con successo.');
    }

    /**
     * Verifica che l'utente possa accedere alla transazione ricorrente.
     */
    private function authorizeRecurringTransaction(RecurringTransaction $recurringTransaction): void
    {
        $user = Auth::user();
        $account = $recurringTransaction->account;

        // Deve appartenere alla household attiva
        if ($account->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questa transazione ricorrente.');
        }
    }

    /**
     * Calcola la prossima data di scadenza.
     */
    private function calculateNextDueDate(RecurringTransaction $rt): ?Carbon
    {
        $today = Carbon::today();
        $startDate = $rt->start_date->copy();
        $endDate = $rt->end_date;

        // Se la data di fine è passata, non c'è prossima scadenza
        if ($endDate && $endDate->lt($today)) {
            return null;
        }

        // Se la data di inizio è nel futuro, quella è la prossima
        if ($startDate->gt($today)) {
            return $startDate;
        }

        // Calcola la prossima occorrenza basandosi sulla frequenza
        $next = $startDate->copy();
        
        while ($next->lte($today)) {
            switch ($rt->frequency) {
                case 'daily':
                    $next->addDay();
                    break;
                case 'weekly':
                    $next->addWeek();
                    break;
                case 'monthly':
                    $next->addMonth();
                    break;
                case 'yearly':
                    $next->addYear();
                    break;
            }
        }

        // Se supera la data di fine, non c'è prossima scadenza
        if ($endDate && $next->gt($endDate)) {
            return null;
        }

        return $next;
    }

    /**
     * Verifica se la transazione ricorrente è ancora attiva.
     */
    private function isActive(RecurringTransaction $rt): bool
    {
        $today = Carbon::today();
        
        // Non ancora iniziata
        if ($rt->start_date->gt($today)) {
            return true; // Considerata attiva ma non ancora partita
        }

        // Ha una data di fine ed è passata
        if ($rt->end_date && $rt->end_date->lt($today)) {
            return false;
        }

        return true;
    }
}
