<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function __construct(
        private TransferService $transferService
    ) {}

    /**
     * Mostra l'elenco dei trasferimenti della household attiva.
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()
            ->route('transactions.index')
            ->with('success', 'I trasferimenti ora sono gestiti da Conti e movimenti.');
    }

    /**
     * Mostra il form per creare un nuovo trasferimento.
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
            ->get()
            ->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'currency_code' => $account->currency_code,
                'current_balance' => (float) $account->current_balance,
            ]);

        return Inertia::render('Transfers/Create', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Salva un nuovo trasferimento.
     */
    public function store(StoreTransferRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();
        $householdId = $user->active_household_id;

        // Recupera o crea le categorie per trasferimento
        $transferOutCategory = Category::firstOrCreate(
            ['household_id' => $householdId, 'name' => 'Trasferimento in Uscita'],
            ['type' => 'expense', 'color' => '#6366f1', 'icon' => '↗️']
        );

        $transferInCategory = Category::firstOrCreate(
            ['household_id' => $householdId, 'name' => 'Trasferimento in Entrata'],
            ['type' => 'income', 'color' => '#6366f1', 'icon' => '↙️']
        );

        $sourceAccount = Account::find($validated['source_account_id']);
        $destAccount = Account::find($validated['destination_account_id']);

        $this->transferService->createTransfer([
            'source_account_id' => $validated['source_account_id'],
            'destination_account_id' => $validated['destination_account_id'],
            'source_amount' => $validated['amount'],
            'source_currency' => $sourceAccount->currency_code,
            'dest_currency' => $destAccount->currency_code,
            'exchange_rate' => $validated['exchange_rate'] ?? null,
            'fee' => $validated['fee'] ?? null,
            'initiated_by' => $user->id,
            'source_category_id' => $transferOutCategory->id,
            'dest_category_id' => $transferInCategory->id,
            'date' => $validated['date'] ?? now()->toDateString(),
            'description' => $validated['description'] ?? null,
            'is_private' => $validated['is_private'] ?? false,
        ]);

        return redirect()
            ->route('transfers.index')
            ->with('success', 'Trasferimento completato con successo.');
    }

    /**
     * Mostra i dettagli di un trasferimento.
     */
    public function show(Transfer $transfer): Response
    {
        $this->authorizeTransfer($transfer);

        $transfer->load([
            'sourceAccount:id,name,currency_code',
            'destinationAccount:id,name,currency_code',
            'user:id,name',
            'transactions.category:id,name,icon',
        ]);

        return Inertia::render('Transfers/Show', [
            'transfer' => [
                'id' => $transfer->id,
                'uuid' => $transfer->uuid,
                'source_amount' => (float) $transfer->source_amount,
                'source_currency' => $transfer->source_currency,
                'dest_amount' => (float) $transfer->dest_amount,
                'dest_currency' => $transfer->dest_currency,
                'exchange_rate' => $transfer->exchange_rate ? (float) $transfer->exchange_rate : null,
                'fee' => $transfer->fee ? (float) $transfer->fee : null,
                'status' => $transfer->status,
                'created_at' => $transfer->created_at->format('d/m/Y H:i'),
                'source_account' => $transfer->sourceAccount,
                'destination_account' => $transfer->destinationAccount,
                'user' => $transfer->user,
                'transactions' => $transfer->transactions,
            ],
        ]);
    }

    /**
     * Annulla un trasferimento (soft delete).
     */
    public function destroy(Transfer $transfer): RedirectResponse
    {
        $this->authorizeTransfer($transfer);

        // Il modello Transfer già gestisce la cancellazione delle transazioni collegate
        $transfer->delete();

        return redirect()
            ->route('transfers.index')
            ->with('success', 'Trasferimento annullato con successo.');
    }

    /**
     * Verifica che l'utente possa accedere al trasferimento.
     */
    private function authorizeTransfer(Transfer $transfer): void
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $sourceAccount = $transfer->sourceAccount;
        $destAccount = $transfer->destinationAccount;

        // Almeno uno dei conti deve appartenere alla household attiva
        $hasAccess = false;
        if ($sourceAccount && $sourceAccount->household_id === $householdId) {
            $hasAccess = true;
        }
        if ($destAccount && $destAccount->household_id === $householdId) {
            $hasAccess = true;
        }

        if (! $hasAccess) {
            abort(403, 'Non hai accesso a questo trasferimento.');
        }
    }
}
