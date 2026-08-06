<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDebtCreditRequest;
use App\Http\Requests\UpdateDebtCreditRequest;
use App\Models\Currency;
use App\Models\DebtCredit;
use App\Models\DebtCreditAdjustment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            ->map(fn ($dc) => [
                'id' => $dc->id,
                'counterparty' => $dc->counterparty,
                'amount' => $dc->amount,
                'initial_amount' => $dc->initial_amount,
                'paid_amount' => $dc->paid_amount,
                'remaining_amount' => $dc->getRemainingAmount(),
                'interest_rate' => $dc->interest_rate,
                'tan_rate' => $dc->tan_rate,
                'taeg_rate' => $dc->taeg_rate,
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
            ->map(fn ($c) => [
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
        /** @var User $user */
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
            'tan_rate' => $validated['tan_rate'] ?? null,
            'taeg_rate' => $validated['taeg_rate'] ?? null,
            'interest_type' => $validated['interest_type'] ?? 'simple',
            'interest_calculation_date' => $validated['interest_calculation_date'] ?? now(),
            'start_date' => $validated['start_date'] ?? null,
        ]);

        return redirect()
            ->route('debts-credits.index')
            ->with('success', self::TYPES[$validated['type']].' creato con successo.');
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
        }, 'adjustments.user:id,name']);

        $transactions = $debts_credit->transactions->map(fn ($t) => [
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
                'tan_rate' => $debts_credit->tan_rate,
                'taeg_rate' => $debts_credit->taeg_rate,
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
                'start_date' => $debts_credit->start_date?->format('Y-m-d'),
                'status' => $debts_credit->status,
                'status_label' => self::STATUSES[$debts_credit->status],
                'description' => $debts_credit->description,
                'created_by' => $debts_credit->user->name,
                'created_at' => $debts_credit->created_at->format('d/m/Y H:i'),
                'updated_at' => $debts_credit->updated_at->format('d/m/Y H:i'),
                'adjustments' => $debts_credit->adjustments->map(fn ($adj) => [
                    'id' => $adj->id,
                    'amount' => (float) $adj->amount,
                    'effective_date' => $adj->effective_date?->format('Y-m-d'),
                    'reason' => $adj->reason,
                    'notes' => $adj->notes,
                    'user' => $adj->user?->name,
                ])->values()->all(),
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
            ->map(fn ($c) => [
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
                'start_date' => $debts_credit->start_date?->format('Y-m-d'),
                'status' => $debts_credit->status,
                'description' => $debts_credit->description,
                'interest_rate' => $debts_credit->interest_rate,
                'tan_rate' => $debts_credit->tan_rate,
                'taeg_rate' => $debts_credit->taeg_rate,
                'interest_type' => $debts_credit->interest_type,
                'interest_calculation_date' => $debts_credit->interest_calculation_date?->format('Y-m-d'),
                'has_linked_transactions' => $debts_credit->transactions()->exists(),
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

        $validated = $request->validated();

        if ($debts_credit->transactions()->exists()) {
            if ($validated['type'] !== $debts_credit->type) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['type' => 'Non puoi cambiare il tipo: esistono transazioni collegate.']);
            }
            if ($validated['currency_code'] !== $debts_credit->currency_code) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['currency_code' => 'Non puoi cambiare la valuta: esistono transazioni collegate.']);
            }
        }

        $paid = (float) $debts_credit->paid_amount;
        $newAmount = (float) $validated['amount'];

        if ($paid > 0 && $newAmount < $paid) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['amount' => 'L\'importo non può essere inferiore a quanto già pagato ('.number_format($paid, 2, ',', '.').').']);
        }

        $data = $validated;
        $data['initial_amount'] = $newAmount;
        if ($paid === 0.0) {
            $data['amount'] = $newAmount;
        }

        $debts_credit->update($data);

        return redirect()
            ->route('debts-credits.index')
            ->with('success', self::TYPES[$debts_credit->type].' aggiornato con successo.');
    }

    public function addAdjustment(Request $request, DebtCredit $debts_credit): RedirectResponse
    {
        $this->authorizeDebtCredit($debts_credit);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'effective_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DebtCreditAdjustment::create([
            'debt_credit_id' => $debts_credit->id,
            'user_id' => Auth::id(),
            'amount' => $validated['amount'],
            'effective_date' => $validated['effective_date'],
            'reason' => $validated['reason'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $totalPaid = Transaction::where('debt_credit_id', $debts_credit->id)->sum(DB::raw('ABS(amount)'));
        $totalAdjusted = DebtCreditAdjustment::where('debt_credit_id', $debts_credit->id)->sum('amount');
        $debts_credit->paid_amount = (float) $totalPaid + (float) $totalAdjusted;
        $remaining = $debts_credit->getRemainingAmount();
        if ($remaining <= 0.01) {
            $debts_credit->status = 'closed';
        } elseif ($debts_credit->due_date && now()->isAfter($debts_credit->due_date)) {
            $debts_credit->status = 'overdue';
        } else {
            $debts_credit->status = 'open';
        }
        $debts_credit->save();

        return redirect()->route('debts-credits.show', $debts_credit->id)
            ->with('success', 'Riduzione non monetaria registrata con successo.');
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
            ->with('success', self::TYPES[$debts_credit->type].' chiuso con successo.');
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
            ->with('success', self::TYPES[$debts_credit->type].' riaperto con successo.');
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
            ->with('success', self::TYPES[$type].' eliminato con successo.');
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
