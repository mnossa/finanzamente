<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DebtCredit;
use App\Models\InterHouseholdTransfer;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Services\AccountBalanceService;
use App\Services\CurrencyConverter;
use App\Services\TransactionQuickChipService;
use App\Services\TransactionSplitService;
use App\Services\UpcomingCashflowService;
use App\Support\TransactionDescriptionFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /** Chiavi ammesse per tornare all'indice transazioni con filtri/pagina. */
    private const INDEX_RETURN_QUERY_KEYS = [
        'account_id',
        'category_id',
        'type',
        'from',
        'to',
        'is_tax_deductible',
        'tag_id',
        'description',
        'amount_min',
        'amount_max',
        'currency_code',
        'page',
    ];

    public function __construct(
        private CurrencyConverter $currency,
        private TransactionQuickChipService $quickChips,
    ) {}

    /**
     * Normalizza i parametri di query per redirect verso `transactions.index`
     * (da URL in GET modifica o da JSON/array nel body dopo POST/PATCH/DELETE).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, int|string>
     */
    private function sanitizeReturnIndexQuery(array $input): array
    {
        $out = [];

        if (isset($input['account_id']) && filter_var($input['account_id'], FILTER_VALIDATE_INT)) {
            $out['account_id'] = (int) $input['account_id'];
        }

        if (isset($input['category_id'])) {
            $cv = $input['category_id'];
            if ($cv === '__none__') {
                $out['category_id'] = '__none__';
            } elseif (filter_var($cv, FILTER_VALIDATE_INT)) {
                $out['category_id'] = (int) $cv;
            }
        }

        if (isset($input['type']) && in_array($input['type'], ['income', 'expense'], true)) {
            $out['type'] = $input['type'];
        }

        if (! empty($input['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['from'])) {
            $out['from'] = (string) $input['from'];
        }
        if (! empty($input['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['to'])) {
            $out['to'] = (string) $input['to'];
        }

        if (isset($input['is_tax_deductible'])) {
            $v = $input['is_tax_deductible'];
            $s = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
            if (in_array($s, ['true', 'false'], true)) {
                $out['is_tax_deductible'] = $s;
            }
        }

        if (isset($input['tag_id']) && filter_var($input['tag_id'], FILTER_VALIDATE_INT)) {
            $out['tag_id'] = (int) $input['tag_id'];
        }

        if (isset($input['page'])) {
            $p = filter_var($input['page'], FILTER_VALIDATE_INT);
            if ($p !== false && $p >= 1) {
                $out['page'] = $p;
            }
        }

        if (! empty($input['description']) && is_string($input['description'])) {
            $desc = trim($input['description']);
            if ($desc !== '' && mb_strlen($desc) <= 120) {
                $out['description'] = $desc;
            }
        }

        if (! empty($out['description']) && ! empty($input['description_regex'])) {
            $regexFlag = $input['description_regex'];
            if (in_array((string) $regexFlag, ['1', 'true', 'on'], true) || $regexFlag === true || $regexFlag === 1) {
                $out['description_regex'] = '1';
            }
        }

        if (isset($input['amount_min']) && $input['amount_min'] !== '' && is_numeric($input['amount_min'])) {
            $min = (float) $input['amount_min'];
            if ($min >= 0) {
                $out['amount_min'] = (string) round($min, 2);
            }
        }

        if (isset($input['amount_max']) && $input['amount_max'] !== '' && is_numeric($input['amount_max'])) {
            $max = (float) $input['amount_max'];
            if ($max >= 0) {
                $out['amount_max'] = (string) round($max, 2);
            }
        }

        if (! empty($input['currency_code']) && is_string($input['currency_code']) && preg_match('/^[A-Za-z]{3}$/', $input['currency_code'])) {
            $out['currency_code'] = strtoupper((string) $input['currency_code']);
        }

        return $out;
    }

    /**
     * Query di ritorno all'indice dopo create/update/delete: mantiene filtri ma riparte da pagina 1.
     *
     * @return array<string, int|string>
     */
    private function returnIndexQueryAfterMutation(Request $request): array
    {
        $query = $this->returnIndexQueryFromRequest($request);
        unset($query['page']);

        return $query;
    }

    /**
     * Legge `return_index_query` dal body (stringa JSON o array) e restituisce query sicura per redirect.
     *
     * @return array<string, int|string>
     */
    private function returnIndexQueryFromRequest(Request $request): array
    {
        $raw = $request->input('return_index_query');
        if (is_array($raw)) {
            return $this->sanitizeReturnIndexQuery($raw);
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $this->sanitizeReturnIndexQuery($decoded) : [];
        }

        return [];
    }

    /**
     * Parametri di ritorno dall'URL della richiesta GET (es. link modifica con filtri in query).
     *
     * @return array<string, int|string>
     */
    private function indexQueryFromCurrentUrl(Request $request): array
    {
        $allowed = array_flip(self::INDEX_RETURN_QUERY_KEYS);
        $fromQuery = [];
        foreach ($request->query() as $key => $value) {
            if (! isset($allowed[$key]) || $value === null || $value === '') {
                continue;
            }
            $fromQuery[$key] = $value;
        }

        return $this->sanitizeReturnIndexQuery($fromQuery);
    }

    /**
     * Calcola i campi multi-currency da salvare sulla transazione, gestendo
     * il caso in cui l'utente abbia indicato di aver pagato in valuta diversa
     * (campi `original_amount` + `original_currency_code` + `manual_rate` opzionale
     * provenienti dal form). Se non c'è override, fa solo lo snapshot del rate
     * verso EUR per la valuta del conto.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed> Sotto-payload da fondere con i campi base.
     */
    private function applyCurrencyFields(array $validated, Account $account, Carbon $date, float $signedAmount): array
    {
        $accountCurrency = $account->currency_code ?: CurrencyConverter::BASE_CURRENCY;

        // Caso "ho pagato in valuta diversa dal conto"
        if (! empty($validated['original_amount']) && ! empty($validated['original_currency_code'])) {
            $converted = $this->currency->convertToAccountCurrency(
                originalAmount: (float) $validated['original_amount'],
                originalCurrency: (string) $validated['original_currency_code'],
                accountCurrency: $accountCurrency,
                date: $date,
                manualRate: ! empty($validated['manual_rate']) ? (float) $validated['manual_rate'] : null,
            );

            // Il `signedAmount` calcolato dal controller è già nella valuta del conto
            // (l'utente ha digitato l'importo che la banca ha addebitato/accreditato).
            // `original_*` è informativo. Il rate-to-base resta legato a accountCurrency.
            return [
                'currency_code' => $accountCurrency,
                'exchange_rate_to_base' => $converted['exchange_rate_to_base'],
                'amount_base' => $signedAmount >= 0
                    ? abs((float) $signedAmount * (float) $converted['exchange_rate_to_base'])
                    : -abs((float) $signedAmount * (float) $converted['exchange_rate_to_base']),
                'original_amount' => round((float) $validated['original_amount'], 2),
                'original_currency_code' => strtoupper((string) $validated['original_currency_code']),
            ];
        }

        // Caso normale: importo già nella valuta del conto, niente origine alternativa
        $snapshot = $this->currency->snapshot(abs($signedAmount), $accountCurrency, $date);

        return [
            'currency_code' => $accountCurrency,
            'exchange_rate_to_base' => $snapshot['exchange_rate_to_base'],
            'amount_base' => $signedAmount >= 0
                ? abs((float) $signedAmount * (float) $snapshot['exchange_rate_to_base'])
                : -abs((float) $signedAmount * (float) $snapshot['exchange_rate_to_base']),
            'original_amount' => null,
            'original_currency_code' => null,
        ];
    }

    /**
     * Mostra l'elenco delle transazioni della household attiva.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $query = Transaction::with([
            'account:id,name,currency_code',
            'category:id,name,color,icon,type',
            'user:id,name',
            'tags:id,name,color',
            'recurringTransaction:id,description,frequency',
            'investment.investmentPac.asset:id,name',
        ])
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
            } elseif ($request->type === 'investment') {
                $query->whereNotNull('investment_id');
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
        if ($request->filled('currency_code')) {
            $query->where('currency_code', strtoupper((string) $request->currency_code));
        }

        TransactionDescriptionFilter::apply(
            $query,
            $request->input('description'),
            $request->boolean('description_regex')
        );

        $amountMin = $request->filled('amount_min') && is_numeric($request->amount_min)
            ? (float) $request->amount_min
            : null;
        $amountMax = $request->filled('amount_max') && is_numeric($request->amount_max)
            ? (float) $request->amount_max
            : null;
        if ($amountMin !== null || $amountMax !== null) {
            $this->applyAbsoluteAmountRangeFilter($query, $amountMin, $amountMax);
        }

        $filterQueryKeys = ['account_id', 'category_id', 'type', 'from', 'to', 'is_tax_deductible', 'tag_id', 'description', 'description_regex', 'amount_min', 'amount_max', 'currency_code'];

        $today = Carbon::today();
        $showUpcoming = ! $request->filled('from') && ! $request->filled('to') && ! $request->filled('type');

        if ($showUpcoming) {
            $query->whereDate('date', '<=', $today);
        }

        $summaryQuery = clone $query;
        $summaryIncome = (float) (clone $summaryQuery)->where('transactions.amount', '>', 0)->sum('transactions.amount');
        $summaryExpenses = (float) abs((float) (clone $summaryQuery)->where('transactions.amount', '<', 0)->sum('transactions.amount'));
        $summaryCount = (int) (clone $summaryQuery)->count();

        $householdAccounts = Account::query()
            ->where('household_id', $householdId)
            ->where('active', true)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('owner_user_id', $user->id))
            ->get();
        $balanceService = app(AccountBalanceService::class);
        $settledBalances = $balanceService->batchComputeBalances($householdAccounts, $user);
        $futureTransactionsByAccount = Transaction::query()
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->whereDate('date', '>', $today)
            ->orderBy('date')
            ->orderBy('created_at')
            ->get(['id', 'account_id', 'amount', 'date'])
            ->groupBy('account_id');

        $transactions = $query
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->appends($request->only($filterQueryKeys))
            ->through(function ($transaction) use ($today, $settledBalances, $futureTransactionsByAccount) {
                $isFuture = $transaction->date->isAfter($today);
                $projectedBalanceAfter = null;

                if ($isFuture) {
                    $balance = (float) ($settledBalances[$transaction->account_id] ?? 0.0);
                    foreach ($futureTransactionsByAccount->get($transaction->account_id, collect()) as $futureTx) {
                        if ($futureTx->date->lte($transaction->date)) {
                            $balance += (float) $futureTx->amount;
                        }
                    }
                    $projectedBalanceAfter = round($balance, 2);
                }

                return [
                    'id' => $transaction->id,
                    'amount' => (float) $transaction->amount,
                    'date' => $transaction->date->format('Y-m-d'),
                    'is_future' => $isFuture,
                    'projected_balance_after' => $projectedBalanceAfter,
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
                    'recurring_transaction_id' => $transaction->recurring_transaction_id,
                    'recurring_summary' => $transaction->recurringTransaction ? [
                        'id' => $transaction->recurringTransaction->id,
                        'description' => $transaction->recurringTransaction->description,
                        'frequency' => $transaction->recurringTransaction->frequency,
                    ] : null,
                    'investment_id' => $transaction->investment_id,
                    'is_investment' => $transaction->investment_id !== null,
                    ...$this->transactionPacFields($transaction),
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
            ->orderBy('counterparty')
            ->orderBy('description')
            ->get(['id', 'description', 'counterparty', 'type']);

        $tags = Tag::forUser($user->id, $householdId)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $accountFilter = $request->filled('account_id') ? (int) $request->account_id : null;
        $upcomingService = app(UpcomingCashflowService::class);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'upcomingMovements' => $showUpcoming ? $upcomingService->buildUpcomingMovements($user, $accountFilter) : [],
            'projectedHouseholdBalance' => $showUpcoming ? $upcomingService->projectedHouseholdBalance($user) : null,
            'accounts' => $accounts,
            'categories' => $categories,
            'debtCredits' => $debtCredits,
            'tags' => $tags,
            'filters' => $request->only($filterQueryKeys),
            'activeImports' => TransactionImport::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'processing'])
                ->orderBy('created_at', 'desc')
                ->get(['id', 'status', 'rows_total', 'rows_imported', 'created_at']),
            'summary' => [
                'count' => $summaryCount,
                'income' => $summaryIncome,
                'expenses' => $summaryExpenses,
                'net' => $summaryIncome - $summaryExpenses,
            ],
            'currencies' => $this->currencyOptions(),
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

        $tags = Tag::forUser($user->id, $householdId)
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
            'currencies' => $this->currencyOptions(),
            'userDefaultCurrency' => $user->default_currency_code ?? CurrencyConverter::BASE_CURRENCY,
            'defaultAccountId' => $request->query('account_id'),
            'defaultDebtCreditId' => $request->query('debt_credit_id'),
            'quickChips' => $this->quickChips->forUser($user),
        ]);
    }

    /**
     * Lista delle valute esposte ai form: { code, name, symbol }.
     *
     * @return array<int, array{code: string, name: string, symbol: ?string}>
     */
    private function currencyOptions(): array
    {
        return Currency::orderBy('code')
            ->get(['code', 'name', 'symbol'])
            ->map(fn ($c) => [
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
            ])
            ->all();
    }

    /**
     * Anteprima rate di cambio per il form transazioni (chiamata AJAX).
     *
     * Ritorna il tasso 1 unità di `from` → `to` alla data indicata, sfruttando
     * la cache `exchange_rates` (e Frankfurter solo in caso di cache miss). Il
     * frontend usa questo valore come hint per il campo "Cambio manuale" così
     * l'utente vede subito il rate suggerito prima del submit.
     *
     * Risposta JSON:
     *   { rate, source, effective_date, from, to }
     */
    public function fxPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'to' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'date' => ['nullable', 'date'],
        ]);

        $from = strtoupper($validated['from']);
        $to = strtoupper($validated['to']);
        $date = isset($validated['date']) ? Carbon::parse($validated['date']) : Carbon::today();

        $conversion = $this->currency->convertToAccountCurrency(
            originalAmount: 1.0,
            originalCurrency: $from,
            accountCurrency: $to,
            date: $date,
        );

        // `convertToAccountCurrency` restituisce `amount` = 1 unità di `from`
        // espresso in `to`. Quando le due valute coincidono il rate è 1 per
        // costruzione e non c'è bisogno di alcuna chiamata esterna.
        return response()->json([
            'from' => $from,
            'to' => $to,
            'date' => $date->toDateString(),
            'rate' => (float) $conversion['amount'],
            'source' => $from === $to ? 'identity' : 'exchange_rates',
        ]);
    }

    /**
     * Salva una nuova transazione.
     */
    public function store(StoreTransactionRequest $request, TransactionSplitService $splitService): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Determina il segno dell'importo in base al tipo di categoria
        $category = Category::find($validated['category_id']);

        if (! empty($validated['splits']) && count($validated['splits']) >= 2) {
            $tagIds = $this->resolveTagIds(
                $validated['tag_ids'] ?? [],
                $validated['new_tag_names'] ?? [],
                $user->active_household_id,
                $user->id
            );
            $validated['tag_ids'] = $tagIds;

            $transactions = $splitService->createSplit($user, $validated, $validated['splits'], $category);
            $primary = $transactions->first();

            if ($primary?->debt_credit_id) {
                return redirect()
                    ->route('debts-credits.show', $primary->debt_credit_id)
                    ->with('success', 'Pagamento diviso registrato con successo.');
            }

            return redirect()
                ->route('transactions.index')
                ->with('success', 'Pagamento diviso registrato con successo.');
        }
        $amount = abs($validated['amount']);
        if ($category && $category->type === 'expense') {
            $amount = -$amount;
        }

        $account = Account::find($validated['account_id']);
        $currencyFields = $this->applyCurrencyFields(
            $validated,
            $account,
            Carbon::parse($validated['date']),
            $amount,
        );

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $validated['account_id'],
            'category_id' => $validated['category_id'],
            'amount' => $amount,
            'date' => $validated['date'],
            'description' => $validated['description'] ?? null,
            'is_private' => $validated['is_private'] ?? false,
            'debt_credit_id' => $validated['debt_credit_id'] ?? null,
            'is_tax_deductible' => $validated['is_tax_deductible'] ?? false,
            'tax_deduction_rate' => $validated['tax_deduction_rate'] ?? null,
            'tax_deduction_type' => $validated['tax_deduction_type'] ?? null,
            'tax_year' => $validated['tax_year'] ?? (($validated['is_tax_deductible'] ?? false) ? Carbon::parse($validated['date'])->year : null),
            ...$currencyFields,
        ]);

        // Sincronizza i tag (esistenti + nuovi da creare)
        $tagIds = $this->resolveTagIds(
            $validated['tag_ids'] ?? [],
            $validated['new_tag_names'] ?? [],
            $user->active_household_id,
            $user->id
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
    public function show(Request $request, Transaction $transaction): Response
    {
        $this->authorizeTransaction($transaction);

        $transaction->load([
            'account:id,name,currency_code',
            'category:id,name,color,icon,type',
            'user:id,name',
            'tags',
            'refunds.refundTransaction',
            'attachments.uploader:id,name',
            'recurringTransaction:id,description,frequency',
            'investment.investmentPac.asset:id,name',
        ]);

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
                'recurring_transaction_id' => $transaction->recurring_transaction_id,
                'recurring_summary' => $transaction->recurringTransaction ? [
                    'id' => $transaction->recurringTransaction->id,
                    'description' => $transaction->recurringTransaction->description,
                    'frequency' => $transaction->recurringTransaction->frequency,
                ] : null,
                'refund_info' => $refundInfo,
                'investment_id' => $transaction->investment_id,
                'is_investment' => $transaction->isInvestmentLedger(),
                ...$this->transactionPacFields($transaction),
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
            'indexQueryForReturn' => $this->indexQueryFromCurrentUrl($request),
        ]);
    }

    /**
     * Mostra il form per modificare una transazione.
     */
    public function edit(Request $request, Transaction $transaction): Response
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

        $tags = Tag::forUser($user->id, $householdId)
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
                'currency_code' => $transaction->currency_code,
                'original_amount' => $transaction->original_amount !== null ? (float) $transaction->original_amount : null,
                'original_currency_code' => $transaction->original_currency_code,
            ],
            'accounts' => $accounts,
            'categories' => $categories,
            'tags' => $tags,
            'debtsCredits' => $debtsCredits,
            'currencies' => $this->currencyOptions(),
            'userDefaultCurrency' => Auth::user()->default_currency_code ?? CurrencyConverter::BASE_CURRENCY,
            'indexQueryForReturn' => $this->indexQueryFromCurrentUrl($request),
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

        $newAccount = Account::find($validated['account_id']);
        $currencyFields = $this->applyCurrencyFields(
            $validated,
            $newAccount,
            Carbon::parse($validated['date']),
            $newAmount,
        );

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
            ...$currencyFields,
        ]);

        // Sincronizza i tag (esistenti + nuovi da creare)
        $tagIds = $this->resolveTagIds(
            $validated['tag_ids'] ?? [],
            $validated['new_tag_names'] ?? [],
            Auth::user()->active_household_id,
            Auth::id()
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

        $returnQuery = $this->returnIndexQueryAfterMutation($request);

        return redirect()
            ->route('transactions.index', $returnQuery)
            ->with('success', 'Transazione aggiornata con successo.');
    }

    /**
     * Aggiorna in massa i campi selezionati per più transazioni.
     * Solo i campi esplicitamente presenti nella richiesta vengono modificati.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $householdId = (int) $user->active_household_id;

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'is_private' => 'sometimes|boolean',
            'debt_credit_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('debts_credits', 'id')->where(function ($query) use ($householdId) {
                    $query->where('household_id', $householdId);
                }),
            ],
            'is_tax_deductible' => 'sometimes|boolean',
            'account_id' => 'sometimes|integer|exists:accounts,id',
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'new_tag_names' => ['sometimes', 'array'],
            'new_tag_names.*' => ['string', 'max:50'],
            'return_index_query' => ['nullable', 'string', 'max:8192'],
        ]);
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

        $hasTagSync = $request->has('tag_ids') || $request->has('new_tag_names');

        if (empty($fields) && $newAccountId === null && ! $hasTagSync) {
            $returnQuery = $this->returnIndexQueryFromRequest($request);

            return redirect()->route('transactions.index', $returnQuery)
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

            if ($hasTagSync) {
                $tagIds = $this->resolveTagIds(
                    $request->input('tag_ids', []),
                    $request->input('new_tag_names', []),
                    $householdId,
                    $user->id
                );
                $transaction->tags()->sync($tagIds);
            }
        }

        $count = $transactions->count();

        $returnQuery = $this->returnIndexQueryAfterMutation($request);

        return redirect()->route('transactions.index', $returnQuery)
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
            'return_index_query' => ['nullable', 'string', 'max:8192'],
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

        $investmentLinkedCount = $transactions->filter(fn (Transaction $tx) => $tx->isInvestmentLedger())->count();
        if ($investmentLinkedCount > 0) {
            $returnQuery = $this->returnIndexQueryAfterMutation($request);

            return redirect()
                ->route('transactions.index', $returnQuery)
                ->with('error', $investmentLinkedCount === 1
                    ? 'La transazione selezionata è collegata a un investimento e non può essere eliminata. Rimuovi la posizione dalla sezione Investimenti.'
                    : "{$investmentLinkedCount} transazioni selezionate sono collegate a investimenti e non possono essere eliminate. Rimuovi le posizioni dalla sezione Investimenti.");
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

        $returnQuery = $this->returnIndexQueryAfterMutation($request);

        return redirect()
            ->route('transactions.index', $returnQuery)
            ->with('success', "{$deletedCount} transazioni eliminate con successo.");
    }

    /**
     * Elimina una transazione (soft delete).
     */
    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($transaction);

        if ($transaction->isInvestmentLedger()) {
            $returnQuery = $this->returnIndexQueryAfterMutation($request);

            return redirect()
                ->route('transactions.index', $returnQuery)
                ->with('error', 'Questa transazione è collegata a un investimento e non può essere eliminata. Per rimuoverla, elimina la posizione dalla sezione Investimenti.');
        }

        // Verifica se è parte di un trasferimento inter-household
        $interHouseholdTransfer = InterHouseholdTransfer::where(function ($q) use ($transaction) {
            $q->where('source_transaction_id', $transaction->id)
                ->orWhere('dest_transaction_id', $transaction->id);
        })->first();

        if ($interHouseholdTransfer) {
            // Elimina il trasferimento inter-household (che eliminerà automaticamente entrambe le transazioni)
            $interHouseholdTransfer->delete();

            $returnQuery = $this->returnIndexQueryAfterMutation($request);

            return redirect()
                ->route('transactions.index', $returnQuery)
                ->with('success', 'Transazione e trasferimento inter-household eliminati con successo.');
        }

        // Aggiorna il saldo del conto
        $account = $transaction->account;
        $account->current_balance -= (float) $transaction->amount;
        $account->save();

        $transaction->delete();

        $returnQuery = $this->returnIndexQueryAfterMutation($request);

        return redirect()
            ->route('transactions.index', $returnQuery)
            ->with('success', 'Transazione eliminata con successo.');
    }

    /**
     * Filtra per valore assoluto dell'importo (entrate positive e uscite negative).
     *
     * @param  Builder<Transaction>  $query
     */
    private function applyAbsoluteAmountRangeFilter($query, ?float $min, ?float $max): void
    {
        $query->where(function ($q) use ($min, $max) {
            $q->where(function ($positive) use ($min, $max) {
                $positive->where('transactions.amount', '>=', 0);
                if ($min !== null && $min >= 0) {
                    $positive->where('transactions.amount', '>=', $min);
                }
                if ($max !== null && $max >= 0) {
                    $positive->where('transactions.amount', '<=', $max);
                }
            })->orWhere(function ($negative) use ($min, $max) {
                $negative->where('transactions.amount', '<', 0);
                if ($min !== null && $min >= 0) {
                    $negative->where('transactions.amount', '<=', -$min);
                }
                if ($max !== null && $max >= 0) {
                    $negative->where('transactions.amount', '>=', -$max);
                }
            });
        });
    }

    /**
     * Verifica che l'utente possa accedere alla transazione.
     */
    /**
     * @return array{is_pac: bool, pac_summary: array{id: int, asset_name: string|null}|null}
     */
    private function transactionPacFields(Transaction $transaction): array
    {
        $pac = $transaction->investment?->investmentPac;
        $isPac = $pac !== null;

        return [
            'is_pac' => $isPac,
            'pac_summary' => $isPac ? [
                'id' => $pac->id,
                'asset_name' => $pac->asset?->name,
            ] : null,
        ];
    }

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
     * @param  int  $userId  ID dell'utente proprietario dei tag
     * @return array Array unico di ID tag da sincronizzare
     */
    private function resolveTagIds(array $tagIds, array $newTagNames, int $householdId, int $userId): array
    {
        $tagIds = Tag::forUser($userId, $householdId)
            ->whereIn('id', $tagIds)
            ->pluck('id')
            ->all();

        foreach ($newTagNames as $tagName) {
            $tag = Tag::findByNameForHousehold($tagName, $householdId, $userId)
                ?? Tag::create([
                    'household_id' => $householdId,
                    'user_id' => $userId,
                    'name' => $tagName,
                    'color' => '#6366f1',
                ]);
            $tagIds[] = $tag->id;
        }

        return array_values(array_unique($tagIds));
    }
}
