<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDebtCreditRequest;
use App\Http\Requests\UpdateDebtCreditRequest;
use App\Models\Currency;
use App\Models\DebtCredit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DebtCreditController extends Controller
{
    public const TYPES = [
        'debt' => 'Debito',
        'credit' => 'Credito',
    ];

    public const STATUSES = [
        'open' => 'Aperto',
        'closed' => 'Chiuso',
        'overdue' => 'Scaduto',
    ];

    /**
     * Mostra l'elenco dei debiti/crediti della household attiva.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $debtsCredits = DebtCredit::where('household_id', $householdId)
            ->with(['currency', 'user'])
            ->orderByRaw("CASE status WHEN 'overdue' THEN 0 WHEN 'open' THEN 1 WHEN 'closed' THEN 2 ELSE 3 END")
            ->orderBy('due_date')
            ->get()
            ->map(fn($dc) => [
                'id' => $dc->id,
                'counterparty' => $dc->counterparty,
                'amount' => $dc->amount,
                'initial_amount' => $dc->initial_amount,
                'paid_amount' => $dc->paid_amount,
                'remaining_amount' => $dc->getRemainingAmount(),
                'interest_rate' => $dc->interest_rate,
                'currency' => [
                    'code' => $dc->currency->code,
                    'symbol' => $dc->currency->symbol,
                ],
                'type' => $dc->type,
                'type_label' => self::TYPES[$dc->type],
                'due_date' => $dc->due_date?->format('Y-m-d'),
                'status' => $dc->status,
                'status_label' => self::STATUSES[$dc->status],
                'description' => $dc->description,
                'created_by' => $dc->user->name,
                'created_at' => $dc->created_at->format('Y-m-d'),
            ]);

        // Riepilogo
        $summary = [
            'total_debts' => $debtsCredits->where('type', 'debt')->where('status', '!=', 'closed')->sum('remaining_amount'),
            'total_credits' => $debtsCredits->where('type', 'credit')->where('status', '!=', 'closed')->sum('remaining_amount'),
            'overdue_count' => $debtsCredits->where('status', 'overdue')->count(),
        ];

        return Inertia::render('DebtsCredits/Index', [
            'debtsCredits' => $debtsCredits,
            'summary' => $summary,
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Mostra il form per creare un nuovo debito/credito.
     */
    public function create(): Response
    {
        $currencies = Currency::orderBy('code')
            ->get()
            ->map(fn($c) => [
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
            ]);

        return Inertia::render('DebtsCredits/Create', [
            'currencies' => $currencies,
            'types' => self::TYPES,
        ]);
    }

    /**
     * Salva un nuovo debito/credito.
     */
    public function store(StoreDebtCreditRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Determina lo stato iniziale
        $status = 'open';
        if (isset($validated['due_date']) && $validated['due_date'] < now()->format('Y-m-d')) {
            $status = 'overdue';
        }

        DebtCredit::create([
            'household_id' => $user->active_household_id,
            'user_id' => $user->id,
            'counterparty' => $validated['counterparty'],
            'amount' => $validated['amount'],
            'initial_amount' => $validated['amount'], // Imposta l'importo iniziale
            'paid_amount' => 0, // Inizialmente 0
            'currency_code' => $validated['currency_code'],
            'type' => $validated['type'],
            'due_date' => $validated['due_date'] ?? null,
            'status' => $status,
            'description' => $validated['description'] ?? null,
            'interest_rate' => $validated['interest_rate'] ?? null,
            'interest_type' => $validated['interest_type'] ?? 'simple',
            'interest_calculation_date' => $validated['interest_calculation_date'] ?? now(),
        ]);

        return redirect()
            ->route('debts-credits.index')
            ->with('success', self::TYPES[$validated['type']] . ' creato con successo.');
    }

    /**
     * Mostra i dettagli di un debito/credito.
     */
    public function show(DebtCredit $debts_credit): Response
    {
        $this->authorizeDebtCredit($debts_credit);

        $debts_credit->load(['transactions' => function ($q) {
            $q->with('account:id,name,currency_code', 'category:id,name,icon')
              ->orderBy('date', 'desc')
              ->limit(50);
        }]);

        $transactions = $debts_credit->transactions->map(fn($t) => [
            'id' => $t->id,
            'amount' => (float) $t->amount,
            'date' => $t->date->format('Y-m-d'),
            'description' => $t->description,
            'account' => ['name' => $t->account->name, 'currency_code' => $t->account->currency_code],
            'category' => $t->category ? ['name' => $t->category->name, 'icon' => $t->category->icon] : null,
        ]);

        return Inertia::render('DebtsCredits/Show', [
            'debtCredit' => [
                'id' => $debts_credit->id,
                'counterparty' => $debts_credit->counterparty,
                'amount' => $debts_credit->amount,
                'initial_amount' => $debts_credit->initial_amount,
                'paid_amount' => $debts_credit->paid_amount,
                'remaining_amount' => $debts_credit->getRemainingAmount(),
                'interest_rate' => $debts_credit->interest_rate,
                'interest_type' => $debts_credit->interest_type,
                'interest_calculation_date' => $debts_credit->interest_calculation_date?->format('Y-m-d'),
                'accrued_interest' => $debts_credit->calculateAccruedInterest(),
                'total_with_interest' => $debts_credit->getTotalAmountWithInterest(),
                'currency' => [
                    'code' => $debts_credit->currency->code,
                    'symbol' => $debts_credit->currency->symbol,
                ],
                'type' => $debts_credit->type,
                'type_label' => self::TYPES[$debts_credit->type],
                'due_date' => $debts_credit->due_date?->format('Y-m-d'),
                'status' => $debts_credit->status,
                'status_label' => self::STATUSES[$debts_credit->status],
                'description' => $debts_credit->description,
                'created_by' => $debts_credit->user->name,
                'created_at' => $debts_credit->created_at->format('d/m/Y H:i'),
                'updated_at' => $debts_credit->updated_at->format('d/m/Y H:i'),
            ],
            'transactions' => $transactions,
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Mostra il form per modificare un debito/credito.
     */
    public function edit(DebtCredit $debts_credit): Response
    {
        $this->authorizeDebtCredit($debts_credit);

        $currencies = Currency::orderBy('code')
            ->get()
            ->map(fn($c) => [
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
            ]);

        return Inertia::render('DebtsCredits/Edit', [
            'debtCredit' => [
                'id' => $debts_credit->id,
                'counterparty' => $debts_credit->counterparty,
                'amount' => $debts_credit->amount,
                'currency_code' => $debts_credit->currency_code,
                'type' => $debts_credit->type,
                'due_date' => $debts_credit->due_date?->format('Y-m-d'),
                'status' => $debts_credit->status,
                'description' => $debts_credit->description,
                'interest_rate' => $debts_credit->interest_rate,
                'interest_type' => $debts_credit->interest_type,
                'interest_calculation_date' => $debts_credit->interest_calculation_date?->format('Y-m-d'),
            ],
            'currencies' => $currencies,
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
        ]);
    }

    /**
     * Aggiorna un debito/credito esistente.
     */
    public function update(UpdateDebtCreditRequest $request, DebtCredit $debts_credit): RedirectResponse
    {
        $this->authorizeDebtCredit($debts_credit);

        $debts_credit->update($request->validated());

        return redirect()
            ->route('debts-credits.index')
            ->with('success', self::TYPES[$debts_credit->type] . ' aggiornato con successo.');
    }

    /**
     * Chiude un debito/credito.
     */
    public function close(DebtCredit $debts_credit): RedirectResponse
    {
        $this->authorizeDebtCredit($debts_credit);

        $debts_credit->update(['status' => 'closed']);

        return redirect()
            ->route('debts-credits.index')
            ->with('success', self::TYPES[$debts_credit->type] . ' chiuso con successo.');
    }

    /**
     * Riapre un debito/credito.
     */
    public function reopen(DebtCredit $debts_credit): RedirectResponse
    {
        $this->authorizeDebtCredit($debts_credit);

        $status = 'open';
        if ($debts_credit->due_date && $debts_credit->due_date < now()) {
            $status = 'overdue';
        }

        $debts_credit->update(['status' => $status]);

        return redirect()
            ->route('debts-credits.index')
            ->with('success', self::TYPES[$debts_credit->type] . ' riaperto con successo.');
    }

    /**
     * Elimina un debito/credito.
     */
    public function destroy(DebtCredit $debts_credit): RedirectResponse
    {
        $this->authorizeDebtCredit($debts_credit);

        $type = $debts_credit->type;
        $debts_credit->delete();

        return redirect()
            ->route('debts-credits.index')
            ->with('success', self::TYPES[$type] . ' eliminato con successo.');
    }

    /**
     * Verifica che l'utente possa accedere al debito/credito.
     */
    private function authorizeDebtCredit(DebtCredit $debtCredit): void
    {
        $user = Auth::user();

        if ($debtCredit->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questo elemento.');
        }
    }
}
