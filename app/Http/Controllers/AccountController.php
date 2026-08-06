<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\StoreMealVoucherUnitValueRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdatePensionFundPositionRequest;
use App\Models\Account;
use App\Models\Currency;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\MealVoucherLedgerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountBalanceService $accountBalanceService,
        private readonly MealVoucherLedgerService $mealVoucherLedger,
    ) {}

    /**
     * Mostra l'elenco dei conti della household attiva.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $accountModels = Account::where('household_id', $householdId)
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->with('owner:id,name')
            ->orderBy('name')
            ->get();

        $accounts = $accountModels->map(function ($account) use ($user) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => $account->isSavingsDeposit()
                    ? Account::uiTypes()[Account::SAVINGS_DEPOSIT_TYPE]
                    : (Account::TYPES[$account->type] ?? $account->type),
                'initial_balance' => (float) $account->initial_balance,
                'current_balance' => $this->accountBalanceService->computeBalance($account, $user),
                'currency_code' => $account->currency_code,
                'interest_rate' => $account->interest_rate !== null ? (float) $account->interest_rate : null,
                'active' => $account->active,
                'is_private' => $account->is_private,
                'owner' => $account->owner ? [
                    'id' => $account->owner->id,
                    'name' => $account->owner->name,
                ] : null,
                'created_at' => $account->created_at->format('Y-m-d'),
            ];
        });

        $totalBalance = $this->accountBalanceService->computeHouseholdTotal(
            $user,
            $accountModels->where('active', true),
            includeLocked: true,
        );

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'totalBalance' => $totalBalance,
        ]);
    }

    /**
     * Mostra il form per creare un nuovo conto.
     */
    public function create(): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $currencies = Currency::orderBy('code')->get(['code', 'name', 'symbol']);

        $accountsCount = Account::where('household_id', $user->active_household_id)->count();

        return Inertia::render('Accounts/Create', [
            'accountTypes' => Account::uiTypes(),
            'currencies' => $currencies,
            'defaultCurrency' => 'EUR',
            'accountsCount' => $accountsCount,
            'maxAccounts' => null,
        ]);
    }

    /**
     * Salva un nuovo conto.
     */
    public function store(StoreAccountRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validated();
        $validated['interest_rate'] = isset($validated['interest_rate']) && $validated['interest_rate'] !== ''
            ? $validated['interest_rate']
            : null;
        $validated = $this->normalizeMealVoucherFields($validated);
        $validated = $this->normalizePensionFundFields($validated);

        $account = new Account($validated);
        $account->household_id = $user->active_household_id;
        $account->current_balance = $validated['initial_balance'];

        // Se il conto è privato, assegna l'owner
        if ($validated['is_private'] ?? false) {
            $account->owner_user_id = $user->id;
        }

        $account->save();

        if ($account->isMealVoucher()) {
            $this->mealVoucherLedger->initializeAccount($account);
        }

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

        $currentBalance = $this->accountBalanceService->computeBalance($account, Auth::user());
        $isMealVoucher = $account->isMealVoucher();
        $ticketUnitValue = $isMealVoucher
            ? $this->mealVoucherLedger->unitValueOn($account, now())
            : null;
        $lots = $isMealVoucher ? $this->mealVoucherLedger->lotsPayload($account) : [];
        $ticketCount = $isMealVoucher ? $this->mealVoucherLedger->totalTicketCount($account) : null;
        $unitValueHistory = $isMealVoucher ? $this->mealVoucherLedger->unitValueHistory($account) : [];

        $recentTransactions = $account->transactions()
            ->with(['category:id,name,color,icon', 'user:id,name'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($transaction) use ($isMealVoucher) {
                $amount = (float) $transaction->amount;
                $movements = $isMealVoucher
                    ? $this->mealVoucherLedger->movementsForTransaction($transaction)
                    : [];
                $ticketsDelta = $movements === []
                    ? null
                    : array_sum(array_column($movements, 'quantity'));

                return [
                    'id' => $transaction->id,
                    'amount' => $amount,
                    'date' => $transaction->date->format('Y-m-d'),
                    'description' => $transaction->description,
                    'category' => $transaction->category,
                    'user' => $transaction->user,
                    'tickets_delta' => $ticketsDelta,
                    'meal_voucher_movements' => $movements,
                ];
            });

        return Inertia::render('Accounts/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => $account->isSavingsDeposit()
                    ? Account::uiTypes()[Account::SAVINGS_DEPOSIT_TYPE]
                    : (Account::TYPES[$account->type] ?? $account->type),
                'initial_balance' => (float) $account->initial_balance,
                'current_balance' => $currentBalance,
                'currency_code' => $account->currency_code,
                'interest_rate' => $account->interest_rate !== null ? (float) $account->interest_rate : null,
                'ticket_unit_value' => $ticketUnitValue,
                'ticket_count' => $ticketCount,
                'external_url' => $account->external_url,
                'is_pension_fund' => $account->isPensionFund(),
                'active' => $account->active,
                'is_private' => $account->is_private,
                'created_at' => $account->created_at->format('d/m/Y'),
            ],
            'mealVoucherLots' => $lots,
            'mealVoucherUnitValueHistory' => $unitValueHistory,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    public function storeUnitValue(StoreMealVoucherUnitValueRequest $request, Account $account): RedirectResponse
    {
        $this->authorizeAccount($account);

        if (! $account->isMealVoucher()) {
            abort(404);
        }

        try {
            $this->mealVoucherLedger->scheduleUnitValue(
                $account,
                (float) $request->validated('unit_value'),
                Carbon::parse($request->validated('effective_from')),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['unit_value' => $e->getMessage()]);
        }

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Valore ticket salvato.');
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
                'type' => $account->isSavingsDeposit() ? Account::SAVINGS_DEPOSIT_TYPE : $account->type,
                'initial_balance' => (float) $account->initial_balance,
                'currency_code' => $account->currency_code,
                'interest_rate' => $account->interest_rate !== null ? (float) $account->interest_rate : null,
                'ticket_unit_value' => $account->ticket_unit_value !== null ? (float) $account->ticket_unit_value : null,
                'external_url' => $account->external_url,
                'active' => $account->active,
                'is_private' => $account->is_private,
            ],
            'accountTypes' => Account::uiTypes(),
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
        $validated['interest_rate'] = isset($validated['interest_rate']) && $validated['interest_rate'] !== ''
            ? $validated['interest_rate']
            : null;
        $validated = $this->normalizeMealVoucherFields($validated, $account->type);
        $validated = $this->normalizePensionFundFields($validated, $account->type);

        // Se cambia il saldo iniziale, ricalcola il saldo corrente
        if (isset($validated['initial_balance']) && $validated['initial_balance'] != $account->initial_balance) {
            $difference = $validated['initial_balance'] - $account->initial_balance;
            $validated['current_balance'] = $account->current_balance + $difference;
        }

        // Gestione owner per conto privato
        if (($validated['is_private'] ?? false) && ! $account->is_private) {
            $validated['owner_user_id'] = $user->id;
        } elseif (! ($validated['is_private'] ?? true) && $account->is_private) {
            $validated['owner_user_id'] = null;
        }

        $account->update($validated);

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Conto aggiornato con successo.');
    }

    /**
     * Imposta la posizione assoluta di un fondo pensione (rettifica via initial_balance).
     */
    public function updatePosition(UpdatePensionFundPositionRequest $request, Account $account): RedirectResponse
    {
        $this->authorizeAccount($account);

        if (! $account->isPensionFund()) {
            abort(404);
        }

        $target = round((float) $request->validated('position'), 2);
        $current = $this->accountBalanceService->computeBalance($account, Auth::user());
        $delta = round($target - $current, 2);

        $account->initial_balance = round((float) $account->initial_balance + $delta, 2);
        $account->current_balance = $target;
        $account->save();

        return redirect()
            ->route('accounts.show', $account)
            ->with('success', 'Posizione del fondo aggiornata.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeMealVoucherFields(array $validated, ?string $fallbackType = null): array
    {
        $type = $validated['type'] ?? $fallbackType;

        if ($type === Account::MEAL_VOUCHER_TYPE) {
            $validated['interest_rate'] = null;
            if (array_key_exists('ticket_unit_value', $validated) && $validated['ticket_unit_value'] === '') {
                $validated['ticket_unit_value'] = null;
            }
        } else {
            $validated['ticket_unit_value'] = null;
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePensionFundFields(array $validated, ?string $fallbackType = null): array
    {
        $type = $validated['type'] ?? $fallbackType;

        if ($type === Account::PENSION_FUND_TYPE) {
            $validated['interest_rate'] = null;
            $validated['ticket_unit_value'] = null;
            if (array_key_exists('external_url', $validated) && $validated['external_url'] === '') {
                $validated['external_url'] = null;
            }
        } elseif (array_key_exists('type', $validated) || array_key_exists('external_url', $validated)) {
            if ($type !== Account::PENSION_FUND_TYPE) {
                $validated['external_url'] = null;
            }
        }

        return $validated;
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

        $account->active = ! $account->active;
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
