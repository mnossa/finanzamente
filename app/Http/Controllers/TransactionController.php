<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\DebtCredit;
use App\Models\InterHouseholdTransfer;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionImport;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Mostra l'elenco delle transazioni della household attiva.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $query = Transaction::with(['account:id,name,currency_code', 'category:id,name,color,icon,type', 'user:id,name', 'tags:id,name,color'])
            ->withCount(['refunds', 'attachments'])
            ->withSum(['refunds as total_refunded_amount' => function ($q) {
                $q->where('status', 'completed');
            }], 'amount')
            ->whereHas('account', function ($q) use ($householdId) {
                $q->where('household_id', $householdId);
            })
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            });

        // Filtri
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        if ($request->filled('category_id')) {
            if ($request->category_id === '__none__') {
                $query->whereNull('category_id');
            } else {
                $query->where('category_id', $request->category_id);
            }
        }
        if ($request->filled('type')) {
            if ($request->type === 'income') {
                $query->where('amount', '>', 0);
            } elseif ($request->type === 'expense') {
                $query->where('amount', '<', 0);
            }
        }
        if ($request->filled('from')) {
            $query->where('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->to);
        }
        if ($request->filled('is_tax_deductible')) {
            $query->where('is_tax_deductible', $request->is_tax_deductible === 'true');
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        $transactions = $query
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'amount' => (float) $transaction->amount,
                    'date' => $transaction->date->format('Y-m-d'),
                    'description' => $transaction->description,
                    'is_private' => $transaction->is_private,
                    'is_tax_deductible' => $transaction->is_tax_deductible,
                    'tax_deduction_type' => $transaction->tax_deduction_type,
                    'transfer_id' => $transaction->transfer_id,
                    'refund_id' => $transaction->refund_id,
                    'has_refunds' => $transaction->refunds_count > 0,
                    'total_refunded_amount' => (float) ($transaction->total_refunded_amount ?? 0),
                    'is_fully_refunded' => $transaction->refunds_count > 0 && abs((float) $transaction->amount) <= (float) ($transaction->total_refunded_amount ?? 0),
                    'attachments_count' => $transaction->attachments_count ?? 0,
                    'category' => $transaction->category ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                        'color' => $transaction->category->color,
                        'icon' => $transaction->category->icon,
                        'type' => $transaction->category->type,
                    ] : null,
                    'account' => [
                        'id' => $transaction->account->id,
                        'name' => $transaction->account->name,
                        'currency_code' => $transaction->account->currency_code,
                    ],
                    'user' => [
                        'id' => $transaction->user->id,
                        'name' => $transaction->user->name,
                    ],
                    'tags' => $transaction->tags->map(fn ($tag) => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ]),
                ];
            });

        // Dati per i filtri
        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = Category::where(function ($q) use ($householdId) {
            $q->where('household_id', $householdId)
                ->orWhereNull('household_id');
        })
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'color', 'icon']);

        $debtCredits = DebtCredit::where('household_id', $householdId)
            ->orderBy('description')
            ->get(['id', 'description', 'type']);

        $tags = Tag::where('household_id', $householdId)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'accounts' => $accounts,
            'categories' => $categories,
            'debtCredits' => $debtCredits,
            'tags' => $tags,
            'filters' => $request->only(['account_id', 'category_id', 'type', 'from', 'to', 'is_tax_deductible', 'tag_id']),
            'activeImports' => TransactionImport::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'processing'])
                ->orderBy('created_at', 'desc')
                ->get(['id', 'status', 'rows_total', 'rows_imported', 'created_at']),
        ]);
    }

    /**
     * Mostra il form per creare una nuova transazione.
     */
    public function create(Request $request): Response
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

        $tags = Tag::where('household_id', $householdId)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $debtsCredits = DebtCredit::where('household_id', $householdId)
            ->whereIn('status', ['open', 'overdue'])
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'counterparty', 'amount', 'initial_amount', 'paid_amount', 'type', 'status', 'due_date', 'currency_code'])
            ->map(function ($dc) {
                return [
                    'id' => $dc->id,
                    'counterparty' => $dc->counterparty,
                    'amount' => (float) $dc->amount,
                    'remaining_amount' => $dc->getRemainingAmount(),
                    'type' => $dc->type,
                    'status' => $dc->status,
                    'due_date' => $dc->due_date ? $dc->due_date->format('Y-m-d') : null,
                    'currency_code' => $dc->currency_code,
                ];
            });

        return Inertia::render('Transactions/Create', [
            'accounts' => $accounts,
            'categories' => $categories,
            'tags' => $tags,
            'debtsCredits' => $debtsCredits,
            'defaultAccountId' => $request->query('account_id'),
            'defaultDebtCreditId' => $request->query('debt_credit_id'),
        ]);
    }

    /**
     * Mostra la Sessione Rapida per l'inserimento batch di transazioni.
     *
     * Questa vista permette di inserire rapidamente più transazioni consecutive
     * senza dover navigare avanti e indietro.
     */
    public function quickSession(Request $request): Response
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

        // Recupera le ultime transazioni aggiunte nella sessione corrente
        $sessionIds = $request->session()->get('quick_session_ids', []);
        $sessionTransactions = [];
        if (! empty($sessionIds)) {
            $sessionTransactions = Transaction::with(['category:id,name,color,icon,type', 'account:id,name,currency_code'])
                ->whereIn('id', $sessionIds)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'amount' => (float) $t->amount,
                    'date' => $t->date->format('Y-m-d'),
                    'description' => $t->description,
                    'category' => $t->category ? [
                        'id' => $t->category->id,
                        'name' => $t->category->name,
                        'color' => $t->category->color,
                        'icon' => $t->category->icon,
                        'type' => $t->category->type,
                    ] : null,
                    'account' => [
                        'id' => $t->account->id,
                        'name' => $t->account->name,
                        'currency_code' => $t->account->currency_code,
                    ],
                ]);
        }

        return Inertia::render('Transactions/QuickSession', [
            'accounts' => $accounts,
            'categories' => $categories,
            'sessionTransactions' => $sessionTransactions,
            'defaultAccountId' => $request->query('account_id'),
        ]);
    }

    /**
     * Salva una transazione durante una Sessione Rapida.
     * Traccia l'ID in sessione per mostrare la lista delle transazioni aggiunte.
     */
    public function quickStore(StoreTransactionRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $category = Category::find($validated['category_id']);
        $amount = abs($validated['amount']);
        if ($category && $category->type === 'expense') {
            $amount = -$amount;
        }

        $account = Account::find($validated['account_id']);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $validated['account_id'],
            'category_id' => $validated['category_id'],
            'amount' => $amount,
            'currency_code' => $account->currency_code,
            'date' => $validated['date'],
            'description' => $validated['description'] ?? null,
            'is_private' => $validated['is_private'] ?? false,
        ]);

        $account->current_balance += $amount;
        $account->save();

        // Traccia l'ID nella sessione per mostrarlo nella lista
        $sessionIds = $request->session()->get('quick_session_ids', []);
        $sessionIds[] = $transaction->id;
        $request->session()->put('quick_session_ids', $sessionIds);

        return redirect()
            ->route('transactions.quick-session')
            ->with('success', 'Transazione aggiunta.');
    }

    /**
     * Azzera la lista delle transazioni della sessione rapida corrente.
     */
    public function clearQuickSession(Request $request): RedirectResponse
    {
        $request->session()->forget('quick_session_ids');

        return redirect()
            ->route('transactions.quick-session')
            ->with('success', 'Sessione terminata.');
    }

    /**
     * Salva una nuova transazione.
     */
    public function store(StoreTransactionRequest $request): RedirectResponse
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

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $validated['account_id'],
            'category_id' => $validated['category_id'],
            'amount' => $amount,
            'currency_code' => $account->currency_code,
            'date' => $validated['date'],
            'description' => $validated['description'] ?? null,
            'is_private' => $validated['is_private'] ?? false,
            'debt_credit_id' => $validated['debt_credit_id'] ?? null,
            'is_tax_deductible' => $validated['is_tax_deductible'] ?? false,
            'tax_deduction_rate' => $validated['tax_deduction_rate'] ?? null,
            'tax_deduction_type' => $validated['tax_deduction_type'] ?? null,
            'tax_year' => $validated['tax_year'] ?? (($validated['is_tax_deductible'] ?? false) ? Carbon::parse($validated['date'])->year : null),
        ]);

        // Sincronizza i tag (esistenti + nuovi da creare)
        $tagIds = $this->resolveTagIds(
            $validated['tag_ids'] ?? [],
            $validated['new_tag_names'] ?? [],
            $user->active_household_id
        );
        $transaction->tags()->sync($tagIds);

        // Aggiorna il saldo del conto
        $account->current_balance += $amount;
        $account->save();

        // Se la transazione è collegata a un debito/credito, torna alla sua pagina
        if ($transaction->debt_credit_id) {
            return redirect()
                ->route('debts-credits.show', $transaction->debt_credit_id)
                ->with('success', 'Pagamento registrato con successo.');
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transazione creata con successo.');
    }

    /**
     * Mostra i dettagli di una transazione.
     */
    public function show(Transaction $transaction): Response
    {
        $this->authorizeTransaction($transaction);

        $transaction->load(['account:id,name,currency_code', 'category:id,name,color,icon,type', 'user:id,name', 'tags', 'refunds.refundTransaction', 'attachments.uploader:id,name']);

        // Calcola informazioni sui rimborsi se è una spesa
        $refundInfo = null;
        if ((float) $transaction->amount < 0 && ! $transaction->transfer_id && ! $transaction->refund_id) {
            $totalRefunded = $transaction->getTotalRefundedAmount();
            $originalAmount = abs((float) $transaction->amount);
            $refundInfo = [
                'total_refunded' => $totalRefunded,
                'max_refundable' => $originalAmount - $totalRefunded,
                'refund_percentage' => $originalAmount > 0 ? round(($totalRefunded / $originalAmount) * 100, 1) : 0,
                'refunds' => $transaction->refunds->map(fn ($refund) => [
                    'id' => $refund->id,
                    'amount' => (float) $refund->amount,
                    'date' => $refund->refundTransaction?->date->format('Y-m-d'),
                    'description' => $refund->description,
                    'status' => $refund->status,
                ]),
            ];
        }

        return Inertia::render('Transactions/Show', [
            'transaction' => [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'date' => $transaction->date->format('Y-m-d'),
                'description' => $transaction->description,
                'is_private' => $transaction->is_private,
                'is_tax_deductible' => $transaction->is_tax_deductible,
                'tax_deduction_rate' => $transaction->tax_deduction_rate ? (float) $transaction->tax_deduction_rate : null,
                'tax_deduction_type' => $transaction->tax_deduction_type,
                'tax_year' => $transaction->tax_year,
                'created_at' => $transaction->created_at->format('d/m/Y H:i'),
                'category' => $transaction->category,
                'account' => $transaction->account,
                'user' => $transaction->user,
                'tags' => $transaction->tags,
                'transfer_id' => $transaction->transfer_id,
                'refund_id' => $transaction->refund_id,
                'refund_info' => $refundInfo,
                'attachments' => $transaction->attachments->map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mime_type,
                    'file_size' => $attachment->file_size,
                    'uploaded_at' => $attachment->uploaded_at->format('d/m/Y H:i'),
                    'uploader' => $attachment->uploader ? [
                        'id' => $attachment->uploader->id,
                        'name' => $attachment->uploader->name,
                    ] : null,
                ]),
            ],
        ]);
    }

    /**
     * Mostra il form per modificare una transazione.
     */
    public function edit(Transaction $transaction): Response
    {
        $this->authorizeTransaction($transaction);

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

        $tags = Tag::where('household_id', $householdId)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $debtsCredits = DebtCredit::where('household_id', $householdId)
            ->whereIn('status', ['open', 'overdue'])
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'counterparty', 'amount', 'initial_amount', 'paid_amount', 'type', 'status', 'due_date', 'currency_code'])
            ->map(function ($dc) {
                return [
                    'id' => $dc->id,
                    'counterparty' => $dc->counterparty,
                    'amount' => (float) $dc->amount,
                    'remaining_amount' => $dc->getRemainingAmount(),
                    'type' => $dc->type,
                    'status' => $dc->status,
                    'due_date' => $dc->due_date ? $dc->due_date->format('Y-m-d') : null,
                    'currency_code' => $dc->currency_code,
                ];
            });

        $transaction->load('tags:id,name,color');

        // Verifica se è parte di un trasferimento inter-household
        $isInterHouseholdTransfer = InterHouseholdTransfer::where(function ($q) use ($transaction) {
            $q->where('source_transaction_id', $transaction->id)
                ->orWhere('dest_transaction_id', $transaction->id);
        })->exists();

        return Inertia::render('Transactions/Edit', [
            'transaction' => [
                'id' => $transaction->id,
                'account_id' => $transaction->account_id,
                'category_id' => $transaction->category_id,
                'amount' => abs((float) $transaction->amount),
                'date' => $transaction->date->format('Y-m-d'),
                'description' => $transaction->description,
                'is_private' => $transaction->is_private,
                'is_tax_deductible' => $transaction->is_tax_deductible,
                'tax_deduction_rate' => $transaction->tax_deduction_rate ? (float) $transaction->tax_deduction_rate : null,
                'tax_deduction_type' => $transaction->tax_deduction_type,
                'tax_year' => $transaction->tax_year,
                'tag_ids' => $transaction->tags->pluck('id')->toArray(),
                'tags' => $transaction->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])->toArray(),
                'debt_credit_id' => $transaction->debt_credit_id,
                'transfer_id' => $transaction->transfer_id,
                'is_inter_household_transfer' => $isInterHouseholdTransfer,
            ],
            'accounts' => $accounts,
            'categories' => $categories,
            'tags' => $tags,
            'debtsCredits' => $debtsCredits,
        ]);
    }

    /**
     * Aggiorna una transazione esistente.
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($transaction);

        // Verifica se è parte di un trasferimento inter-household
        $isInterHouseholdTransfer = InterHouseholdTransfer::where(function ($q) use ($transaction) {
            $q->where('source_transaction_id', $transaction->id)
                ->orWhere('dest_transaction_id', $transaction->id);
        })->exists();

        if ($isInterHouseholdTransfer) {
            return redirect()->back()->withErrors([
                'error' => 'Non è possibile modificare una transazione che fa parte di un trasferimento inter-household. Elimina il trasferimento e ricrealo se necessario.',
            ]);
        }

        $validated = $request->validated();
        $oldAmount = (float) $transaction->amount;
        $oldAccountId = $transaction->account_id;

        // Se la transazione è parte di un trasferimento, ci sono restrizioni
        $isTransfer = $transaction->transfer_id !== null;

        if ($isTransfer) {
            // Non è possibile cambiare categoria o conto per le transazioni di trasferimento
            if ((int) $validated['category_id'] !== $transaction->category_id) {
                return redirect()->back()->withErrors([
                    'category_id' => 'Non è possibile modificare la categoria di una transazione di trasferimento.',
                ]);
            }
            if ((int) $validated['account_id'] !== $oldAccountId) {
                return redirect()->back()->withErrors([
                    'account_id' => 'Non è possibile modificare il conto di una transazione di trasferimento.',
                ]);
            }
        }

        // Determina il segno dell'importo
        $category = Category::find($validated['category_id']);
        $newAmount = abs($validated['amount']);
        if ($category && $category->type === 'expense') {
            $newAmount = -$newAmount;
        }

        // Aggiorna la transazione
        $transaction->update([
            'account_id' => $validated['account_id'],
            'category_id' => $validated['category_id'],
            'amount' => $newAmount,
            'date' => $validated['date'],
            'description' => $validated['description'] ?? null,
            'is_private' => $validated['is_private'] ?? false,
            'debt_credit_id' => $validated['debt_credit_id'] ?? null,
            'is_tax_deductible' => $validated['is_tax_deductible'] ?? false,
            'tax_deduction_rate' => $validated['tax_deduction_rate'] ?? null,
            'tax_deduction_type' => $validated['tax_deduction_type'] ?? null,
            'tax_year' => $validated['tax_year'] ?? (($validated['is_tax_deductible'] ?? false) ? Carbon::parse($validated['date'])->year : null),
        ]);

        // Sincronizza i tag (esistenti + nuovi da creare)
        $tagIds = $this->resolveTagIds(
            $validated['tag_ids'] ?? [],
            $validated['new_tag_names'] ?? [],
            Auth::user()->active_household_id
        );
        $transaction->tags()->sync($tagIds);

        // Se è un trasferimento, aggiorna anche la transazione collegata
        if ($isTransfer) {
            $linkedTransaction = Transaction::where('transfer_id', $transaction->transfer_id)
                ->where('id', '!=', $transaction->id)
                ->first();

            if ($linkedTransaction) {
                // Calcola il nuovo importo per la transazione collegata
                // Se questa è uscita (negativa), l'altra è entrata (positiva) e viceversa
                $linkedOldAmount = (float) $linkedTransaction->amount;
                $linkedNewAmount = $oldAmount != 0
                    ? ($linkedOldAmount / abs($oldAmount)) * abs($newAmount)
                    : abs($newAmount);

                // Aggiorna descrizione e privacy, mantieni segno originale dell'importo
                $linkedTransaction->update([
                    'amount' => $linkedNewAmount,
                    'description' => $validated['description'] ?? null,
                    'is_private' => $validated['is_private'] ?? false,
                ]);

                // Aggiorna il saldo del conto collegato
                $linkedAccount = $linkedTransaction->account;
                $linkedAccount->current_balance += ($linkedNewAmount - $linkedOldAmount);
                $linkedAccount->save();
            }
        }

        // Aggiorna i saldi dei conti
        if ($oldAccountId === $validated['account_id']) {
            // Stesso conto: aggiorna la differenza
            $account = Account::find($validated['account_id']);
            $account->current_balance += ($newAmount - $oldAmount);
            $account->save();
        } else {
            // Conti diversi: rimuovi dal vecchio, aggiungi al nuovo
            $oldAccount = Account::find($oldAccountId);
            $oldAccount->current_balance -= $oldAmount;
            $oldAccount->save();

            $newAccount = Account::find($validated['account_id']);
            $newAccount->current_balance += $newAmount;
            $newAccount->save();
        }

        // Se la transazione è collegata a un debito/credito, torna alla sua pagina
        $updatedDebtCreditId = $validated['debt_credit_id'] ?? null;
        if ($updatedDebtCreditId) {
            return redirect()
                ->route('debts-credits.show', $updatedDebtCreditId)
                ->with('success', 'Pagamento aggiornato con successo.');
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transazione aggiornata con successo.');
    }

    /**
     * Aggiorna in massa i campi selezionati per più transazioni.
     * Solo i campi esplicitamente presenti nella richiesta vengono modificati.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'is_private' => 'sometimes|boolean',
            'debt_credit_id' => 'sometimes|nullable|integer|exists:debt_credits,id',
            'is_tax_deductible' => 'sometimes|boolean',
            'account_id' => 'sometimes|integer|exists:accounts,id',
        ]);

        $user = Auth::user();
        $ids = $request->input('ids');

        $transactions = Transaction::with('account')
            ->whereIn('id', $ids)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->whereHas('account', function ($q) use ($user) {
                $q->where('household_id', $user->active_household_id);
            })
            ->get();

        if ($transactions->count() !== count($ids)) {
            abort(403, 'Non hai accesso ad alcune transazioni selezionate.');
        }

        $fields = [];

        if ($request->has('category_id')) {
            $fields['category_id'] = $request->input('category_id');
        }
        if ($request->has('is_private')) {
            $fields['is_private'] = $request->boolean('is_private');
        }
        if ($request->has('debt_credit_id')) {
            $fields['debt_credit_id'] = $request->input('debt_credit_id');
        }
        if ($request->has('is_tax_deductible')) {
            $fields['is_tax_deductible'] = $request->boolean('is_tax_deductible');
        }

        $newAccountId = $request->has('account_id') ? (int) $request->input('account_id') : null;

        if (empty($fields) && $newAccountId === null) {
            return redirect()->route('transactions.index')
                ->with('info', 'Nessuna modifica da applicare.');
        }

        foreach ($transactions as $transaction) {
            // Gestione cambio conto con aggiornamento saldi
            if ($newAccountId !== null && $transaction->account_id !== $newAccountId) {
                $newAccount = Account::where('id', $newAccountId)
                    ->where('household_id', $user->active_household_id)
                    ->first();

                if ($newAccount) {
                    // Storna dal conto vecchio
                    $transaction->account->current_balance -= (float) $transaction->amount;
                    $transaction->account->save();

                    // Accredita sul nuovo conto
                    $newAccount->current_balance += (float) $transaction->amount;
                    $newAccount->save();

                    $fields['account_id'] = $newAccountId;
                }
            }

            $transaction->fill($fields);
            $transaction->save();
        }

        $count = $transactions->count();

        return redirect()->route('transactions.index')
            ->with('success', "{$count} transazioni aggiornate con successo.");
    }

    /**
     * Elimina più transazioni contemporaneamente (bulk delete).
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $user = Auth::user();
        $ids = $request->input('ids');

        $transactions = Transaction::with('account')
            ->whereIn('id', $ids)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->whereHas('account', function ($q) use ($user) {
                $q->where('household_id', $user->active_household_id);
            })
            ->get();

        if ($transactions->count() !== count($ids)) {
            abort(403, 'Non hai accesso ad alcune transazioni selezionate.');
        }

        $deletedCount = 0;
        $processedTransferIds = [];

        foreach ($transactions as $transaction) {
            $interHouseholdTransfer = InterHouseholdTransfer::where(function ($q) use ($transaction) {
                $q->where('source_transaction_id', $transaction->id)
                    ->orWhere('dest_transaction_id', $transaction->id);
            })->first();

            if ($interHouseholdTransfer) {
                if (! in_array($interHouseholdTransfer->id, $processedTransferIds)) {
                    $interHouseholdTransfer->delete();
                    $processedTransferIds[] = $interHouseholdTransfer->id;
                    $deletedCount++;
                }
            } else {
                $account = $transaction->account;
                $account->current_balance -= (float) $transaction->amount;
                $account->save();
                $transaction->delete();
                $deletedCount++;
            }
        }

        return redirect()
            ->route('transactions.index')
            ->with('success', "{$deletedCount} transazioni eliminate con successo.");
    }

    /**
     * Elimina una transazione (soft delete).
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($transaction);

        // Verifica se è parte di un trasferimento inter-household
        $interHouseholdTransfer = InterHouseholdTransfer::where(function ($q) use ($transaction) {
            $q->where('source_transaction_id', $transaction->id)
                ->orWhere('dest_transaction_id', $transaction->id);
        })->first();

        if ($interHouseholdTransfer) {
            // Elimina il trasferimento inter-household (che eliminerà automaticamente entrambe le transazioni)
            $interHouseholdTransfer->delete();

            return redirect()
                ->route('transactions.index')
                ->with('success', 'Transazione e trasferimento inter-household eliminati con successo.');
        }

        // Aggiorna il saldo del conto
        $account = $transaction->account;
        $account->current_balance -= (float) $transaction->amount;
        $account->save();

        $transaction->delete();

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transazione eliminata con successo.');
    }

    /**
     * Verifica che l'utente possa accedere alla transazione.
     */
    private function authorizeTransaction(Transaction $transaction): void
    {
        $user = Auth::user();
        $account = $transaction->account;

        // Deve appartenere alla household attiva
        if ($account->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questa transazione.');
        }

        // Se è privata, deve essere il creatore
        if ($transaction->is_private && $transaction->user_id !== $user->id) {
            abort(403, 'Questa transazione è privata.');
        }
    }

    /**
     * Risolve gli ID dei tag da sincronizzare: restituisce gli ID dei tag esistenti
     * più quelli creati al volo dai nuovi nomi forniti (normalizzati in uppercase,
     * con riuso del tag esistente se il nome corrisponde case-insensitive).
     *
     * @param  array  $tagIds  ID di tag già esistenti da associare
     * @param  array  $newTagNames  Nuovi nomi di tag da creare/trovare
     * @param  int  $householdId  ID della household corrente
     * @return array Array unico di ID tag da sincronizzare
     */
    private function resolveTagIds(array $tagIds, array $newTagNames, int $householdId): array
    {
        foreach ($newTagNames as $tagName) {
            $tag = Tag::findByNameForHousehold($tagName, $householdId)
                ?? Tag::create([
                    'household_id' => $householdId,
                    'name' => $tagName,
                    'color' => '#6366f1',
                ]);
            $tagIds[] = $tag->id;
        }

        return array_unique($tagIds);
    }
}
