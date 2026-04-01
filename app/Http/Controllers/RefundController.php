<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRefundRequest;
use App\Http\Requests\UpdateRefundRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Refund;
use App\Models\Transaction;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

class RefundController extends Controller
{
    public function __construct(
        private RefundService $refundService
    ) {}

    /**
     * Mostra l'elenco dei rimborsi della household attiva.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $refunds = Refund::with([
            'originalTransaction:id,amount,date,description,account_id,category_id',
            'originalTransaction.account:id,name,currency_code',
            'originalTransaction.category:id,name,color,icon',
            'refundTransaction:id,amount,date,description,account_id,category_id,refund_id',
            'refundTransaction.category:id,name,color,icon',
            'user:id,name'
        ])
            ->whereHas('originalTransaction.account', function ($q) use ($householdId) {
                $q->where('household_id', $householdId);
            })
            ->where(function ($q) use ($user) {
                // Può vedere rimborsi se la transazione originale non è privata o se è il creatore
                $q->whereHas('originalTransaction', function ($q2) use ($user) {
                    $q2->where('is_private', false)
                        ->orWhere('user_id', $user->id);
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->through(function ($refund) {
                return [
                    'id' => $refund->id,
                    'uuid' => $refund->uuid,
                    'amount' => (float) $refund->amount,
                    'currency_code' => $refund->currency_code,
                    'status' => $refund->status,
                    'description' => $refund->description,
                    'created_at' => $refund->created_at->format('d/m/Y H:i'),
                    'original_transaction' => $refund->originalTransaction ? [
                        'id' => $refund->originalTransaction->id,
                        'amount' => (float) $refund->originalTransaction->amount,
                        'date' => $refund->originalTransaction->date->format('Y-m-d'),
                        'description' => $refund->originalTransaction->description,
                        'account' => $refund->originalTransaction->account ? [
                            'id' => $refund->originalTransaction->account->id,
                            'name' => $refund->originalTransaction->account->name,
                            'currency_code' => $refund->originalTransaction->account->currency_code,
                        ] : null,
                        'category' => $refund->originalTransaction->category ? [
                            'id' => $refund->originalTransaction->category->id,
                            'name' => $refund->originalTransaction->category->name,
                            'color' => $refund->originalTransaction->category->color,
                            'icon' => $refund->originalTransaction->category->icon,
                        ] : null,
                    ] : null,
                    'refund_transaction' => $refund->refundTransaction ? [
                        'id' => $refund->refundTransaction->id,
                        'amount' => (float) $refund->refundTransaction->amount,
                        'date' => $refund->refundTransaction->date->format('Y-m-d'),
                        'description' => $refund->refundTransaction->description,
                    ] : null,
                    'user' => $refund->user ? [
                        'id' => $refund->user->id,
                        'name' => $refund->user->name,
                    ] : null,
                ];
            });

        return Inertia::render('Refunds/Index', [
            'refunds' => $refunds,
        ]);
    }

    /**
     * Mostra il form per creare un nuovo rimborso.
     */
    public function create(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        // Carica la transazione originale se specificata
        $originalTransaction = null;
        if ($request->filled('transaction_id')) {
            $originalTransaction = Transaction::with(['account:id,name,currency_code', 'category:id,name,color,icon'])
                ->whereHas('account', function ($q) use ($householdId) {
                    $q->where('household_id', $householdId);
                })
                ->where(function ($q) use ($user) {
                    $q->where('is_private', false)
                        ->orWhere('user_id', $user->id);
                })
                ->whereNull('transfer_id')
                ->whereNull('refund_id')
                ->where('amount', '<', 0) // Solo spese
                ->find($request->query('transaction_id'));

            if ($originalTransaction) {
                $alreadyRefunded = $originalTransaction->getTotalRefundedAmount();
                $originalTransaction = [
                    'id' => $originalTransaction->id,
                    'amount' => (float) $originalTransaction->amount,
                    'date' => $originalTransaction->date->format('Y-m-d'),
                    'description' => $originalTransaction->description,
                    'already_refunded' => $alreadyRefunded,
                    'max_refundable' => abs((float) $originalTransaction->amount) - $alreadyRefunded,
                    'account' => [
                        'id' => $originalTransaction->account->id,
                        'name' => $originalTransaction->account->name,
                        'currency_code' => $originalTransaction->account->currency_code,
                    ],
                    'category' => $originalTransaction->category ? [
                        'id' => $originalTransaction->category->id,
                        'name' => $originalTransaction->category->name,
                        'color' => $originalTransaction->category->color,
                        'icon' => $originalTransaction->category->icon,
                    ] : null,
                ];
            }
        }

        // Carica solo le ultime 20 transazioni rimborsabili inizialmente
        // Le altre verranno caricate tramite ricerca asincrona
        $refundableTransactions = $this->getRefundableTransactions($householdId, $user, '', 20);

        // Conta il totale delle transazioni rimborsabili per mostrare info
        $totalRefundableCount = $this->countRefundableTransactions($householdId, $user);

        // Categorie di tipo income per il rimborso
        $categories = Category::where(function ($q) use ($householdId) {
            $q->where('household_id', $householdId)
                ->orWhereNull('household_id');
        })
            ->where('type', 'income')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'icon']);

        return Inertia::render('Refunds/Create', [
            'originalTransaction' => $originalTransaction,
            'refundableTransactions' => $refundableTransactions,
            'totalRefundableCount' => $totalRefundableCount,
            'categories' => $categories,
        ]);
    }

    /**
     * Cerca transazioni rimborsabili (endpoint AJAX).
     */
    public function searchTransactions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $search = $request->query('search', '');
        $limit = min((int) $request->query('limit', 20), 50); // Max 50 risultati

        $transactions = $this->getRefundableTransactions($householdId, $user, $search, $limit);

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    /**
     * Recupera le transazioni rimborsabili con filtro opzionale.
     */
    private function getRefundableTransactions($householdId, $user, string $search = '', int $limit = 20): array
    {
        $query = Transaction::with(['account:id,name,currency_code', 'category:id,name,color,icon'])
            ->whereHas('account', function ($q) use ($householdId) {
                $q->where('household_id', $householdId);
            })
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->whereNull('transfer_id')
            ->whereNull('refund_id')
            ->where('amount', '<', 0);

        // Applica filtro di ricerca se presente
        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('description', 'like', $searchTerm)
                    ->orWhereHas('category', function ($q2) use ($searchTerm) {
                        $q2->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('account', function ($q2) use ($searchTerm) {
                        $q2->where('name', 'like', $searchTerm);
                    });
            });
        }

        return $query->orderBy('date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($tx) {
                $alreadyRefunded = $tx->getTotalRefundedAmount();
                $maxRefundable = abs((float) $tx->amount) - $alreadyRefunded;

                // Escludi le transazioni già completamente rimborsate
                if ($maxRefundable <= 0.01) {
                    return null;
                }

                return [
                    'id' => $tx->id,
                    'amount' => (float) $tx->amount,
                    'date' => $tx->date->format('Y-m-d'),
                    'description' => $tx->description,
                    'already_refunded' => $alreadyRefunded,
                    'max_refundable' => $maxRefundable,
                    'account' => [
                        'id' => $tx->account->id,
                        'name' => $tx->account->name,
                        'currency_code' => $tx->account->currency_code,
                    ],
                    'category' => $tx->category ? [
                        'id' => $tx->category->id,
                        'name' => $tx->category->name,
                        'color' => $tx->category->color,
                        'icon' => $tx->category->icon,
                    ] : null,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Conta il numero totale di transazioni rimborsabili.
     */
    private function countRefundableTransactions($householdId, $user): int
    {
        return Transaction::whereHas('account', function ($q) use ($householdId) {
            $q->where('household_id', $householdId);
        })
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->whereNull('transfer_id')
            ->whereNull('refund_id')
            ->where('amount', '<', 0)
            ->count();
    }

    /**
     * Salva un nuovo rimborso.
     */
    public function store(StoreRefundRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Limite piano Base: massimo 10 rimborsi attivi
        if (!$user->isPro()) {
            $max = config('plans.base_limits.max_refunds', 10);
            $count = Refund::where('user_id', $user->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
            if ($count >= $max) {
                return redirect()->route('refunds.create')
                    ->with('error', "Hai raggiunto il limite di {$max} rimborsi attivi del piano Base. Passa a Pro per rimborsi illimitati.");
            }
        }

        $validated = $request->validated();

        $this->refundService->createRefund([
            'original_transaction_id' => $validated['original_transaction_id'],
            'amount' => $validated['amount'],
            'user_id' => $user->id,
            'category_id' => $validated['category_id'],
            'date' => $validated['date'] ?? now()->toDateString(),
            'description' => $validated['description'] ?? null,
            'is_private' => $validated['is_private'] ?? false,
        ]);

        return redirect()
            ->route('refunds.index')
            ->with('success', 'Rimborso registrato con successo.');
    }

    /**
     * Mostra i dettagli di un rimborso.
     */
    public function show(Refund $refund): Response
    {
        $this->authorizeRefund($refund);

        $refund->load([
            'originalTransaction:id,amount,date,description,account_id,category_id,user_id,is_private',
            'originalTransaction.account:id,name,currency_code',
            'originalTransaction.category:id,name,color,icon',
            'originalTransaction.user:id,name',
            'refundTransaction:id,amount,date,description,account_id,category_id,refund_id,is_private',
            'refundTransaction.account:id,name,currency_code',
            'refundTransaction.category:id,name,color,icon',
            'user:id,name'
        ]);

        // Calcola informazioni aggiuntive sui rimborsi per la transazione originale
        $totalRefunded = $refund->originalTransaction->getTotalRefundedAmount();
        $originalAmount = abs((float) $refund->originalTransaction->amount);

        return Inertia::render('Refunds/Show', [
            'refund' => [
                'id' => $refund->id,
                'uuid' => $refund->uuid,
                'amount' => (float) $refund->amount,
                'currency_code' => $refund->currency_code,
                'status' => $refund->status,
                'description' => $refund->description,
                'created_at' => $refund->created_at->format('d/m/Y H:i'),
                'original_transaction' => [
                    'id' => $refund->originalTransaction->id,
                    'amount' => (float) $refund->originalTransaction->amount,
                    'date' => $refund->originalTransaction->date->format('Y-m-d'),
                    'description' => $refund->originalTransaction->description,
                    'is_private' => $refund->originalTransaction->is_private,
                    'total_refunded' => $totalRefunded,
                    'original_amount' => $originalAmount,
                    'net_amount' => $refund->originalTransaction->getNetAmount(),
                    'refund_percentage' => $originalAmount > 0 ? round(($totalRefunded / $originalAmount) * 100, 1) : 0,
                    'account' => $refund->originalTransaction->account,
                    'category' => $refund->originalTransaction->category,
                    'user' => $refund->originalTransaction->user,
                ],
                'refund_transaction' => $refund->refundTransaction ? [
                    'id' => $refund->refundTransaction->id,
                    'amount' => (float) $refund->refundTransaction->amount,
                    'date' => $refund->refundTransaction->date->format('Y-m-d'),
                    'description' => $refund->refundTransaction->description,
                    'is_private' => $refund->refundTransaction->is_private,
                    'account' => $refund->refundTransaction->account,
                    'category' => $refund->refundTransaction->category,
                ] : null,
                'user' => $refund->user,
            ],
        ]);
    }

    /**
     * Mostra il form per modificare un rimborso.
     */
    public function edit(Refund $refund): Response
    {
        $this->authorizeRefund($refund);

        $refund->load([
            'originalTransaction:id,amount,date,description,account_id,category_id',
            'originalTransaction.account:id,name,currency_code',
            'originalTransaction.category:id,name,color,icon',
            'refundTransaction:id,amount,date,description,is_private,refund_id',
        ]);

        // Calcola il massimo rimborsabile (escludendo questo rimborso)
        $originalAmount = abs((float) $refund->originalTransaction->amount);
        $otherRefunds = Refund::where('original_transaction_id', $refund->original_transaction_id)
            ->where('id', '!=', $refund->id)
            ->where('status', 'completed')
            ->sum('amount');
        $maxRefundable = $originalAmount - (float) $otherRefunds;

        return Inertia::render('Refunds/Edit', [
            'refund' => [
                'id' => $refund->id,
                'amount' => (float) $refund->amount,
                'max_refundable' => $maxRefundable,
                'description' => $refund->description,
                'date' => $refund->refundTransaction?->date->format('Y-m-d'),
                'is_private' => $refund->refundTransaction?->is_private ?? false,
                'original_transaction' => [
                    'id' => $refund->originalTransaction->id,
                    'amount' => (float) $refund->originalTransaction->amount,
                    'date' => $refund->originalTransaction->date->format('Y-m-d'),
                    'description' => $refund->originalTransaction->description,
                    'account' => $refund->originalTransaction->account,
                    'category' => $refund->originalTransaction->category,
                ],
            ],
        ]);
    }

    /**
     * Aggiorna un rimborso esistente.
     */
    public function update(UpdateRefundRequest $request, Refund $refund): RedirectResponse
    {
        $this->authorizeRefund($refund);

        $validated = $request->validated();

        $this->refundService->updateRefund($refund, $validated);

        return redirect()
            ->route('refunds.index')
            ->with('success', 'Rimborso aggiornato con successo.');
    }

    /**
     * Elimina un rimborso (soft delete).
     */
    public function destroy(Refund $refund): RedirectResponse
    {
        $this->authorizeRefund($refund);

        $this->refundService->deleteRefund($refund);

        return redirect()
            ->route('refunds.index')
            ->with('success', 'Rimborso eliminato con successo.');
    }

    /**
     * Verifica che l'utente possa accedere al rimborso.
     */
    private function authorizeRefund(Refund $refund): void
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $originalTransaction = $refund->originalTransaction;

        if (!$originalTransaction) {
            abort(404, 'Rimborso non trovato.');
        }

        $account = $originalTransaction->account;

        // Deve appartenere alla household attiva
        if (!$account || $account->household_id !== $householdId) {
            abort(403, 'Non hai accesso a questo rimborso.');
        }

        // Se la transazione originale è privata, deve essere il creatore
        if ($originalTransaction->is_private && $originalTransaction->user_id !== $user->id) {
            abort(403, 'Questa transazione è privata.');
        }
    }
}
