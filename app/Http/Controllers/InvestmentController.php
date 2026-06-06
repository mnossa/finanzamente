<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentRequest;
use App\Http\Requests\UpdateInvestmentRequest;
use App\Models\Account;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Services\AssetPriceService;
use App\Services\InvestmentMetricsService;
use App\Services\InvestmentTransactionSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentController extends Controller
{
    public function __construct(
        private readonly AssetPriceService $assetPriceService,
        private readonly InvestmentMetricsService $investmentMetricsService,
        private readonly InvestmentTransactionSyncService $investmentTransactionSyncService,
    ) {}

    /**
     * Mostra l'elenco degli investimenti della household attiva.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $rawInvestments = Investment::with([
            'user:id,name',
            'account:id,name',
            'asset:id,name,symbol,type,currency_code',
            'asset.currency:code,symbol',
            'investmentPac:id,investment_asset_id,status',
            'investmentPac.asset:id,name,symbol',
        ])
            ->where('household_id', $householdId)
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->orderByRaw('sell_date IS NULL DESC') // Prima gli aperti
            ->orderBy('buy_date', 'desc')
            ->get();

        // Recupera prezzi correnti per tutti gli asset aperti (con cache 15 min)
        $currentPrices = $this->investmentMetricsService->fetchCurrentPricesForInvestments($rawInvestments);

        $investments = $rawInvestments->map(function ($investment) use ($currentPrices) {
            $symbol = $investment->asset->symbol;
            $currentPrice = ($investment->isOpen() && $symbol && isset($currentPrices[$symbol]))
                ? $currentPrices[$symbol]
                : null;
            $metrics = $this->investmentMetricsService->unrealizedMetrics($investment, $currentPrice);

            return [
                'id' => $investment->id,
                'asset' => [
                    'id' => $investment->asset->id,
                    'name' => $investment->asset->name,
                    'symbol' => $investment->asset->symbol,
                    'type' => $investment->asset->type,
                    'type_label' => $investment->asset->type_label,
                    'type_icon' => $investment->asset->type_icon,
                    'currency' => [
                        'code' => $investment->asset->currency->code ?? $investment->asset->currency_code,
                        'symbol' => $investment->asset->currency->symbol ?? '€',
                    ],
                ],
                'account' => $investment->account ? [
                    'id' => $investment->account->id,
                    'name' => $investment->account->name,
                ] : null,
                'quantity' => (float) $investment->quantity,
                'buy_price' => (float) $investment->buy_price,
                'buy_date' => $investment->buy_date->format('Y-m-d'),
                'sell_price' => $investment->sell_price ? (float) $investment->sell_price : null,
                'sell_date' => $investment->sell_date?->format('Y-m-d'),
                'fees' => $investment->fees ? (float) $investment->fees : null,
                'total_buy_value' => $investment->total_buy_value,
                'total_sell_value' => $investment->total_sell_value,
                'net_profit' => $investment->net_profit,
                'profit_percentage' => $investment->profit_percentage !== null
                    ? round($investment->profit_percentage, 2)
                    : null,
                'current_price' => $metrics['current_price'],
                'current_value' => $metrics['current_value'],
                'unrealized_profit' => $metrics['unrealized_profit'],
                'is_sold' => $investment->isSold(),
                'is_private' => $investment->is_private,
                'notes' => $investment->notes,
                'investment_pac' => $investment->investmentPac ? [
                    'id' => $investment->investmentPac->id,
                    'status' => $investment->investmentPac->status,
                    'asset_name' => $investment->investmentPac->asset?->name,
                    'asset_symbol' => $investment->investmentPac->asset?->symbol,
                ] : null,
                'user' => [
                    'id' => $investment->user->id,
                    'name' => $investment->user->name,
                ],
            ];
        });

        // Separa investimenti aperti e chiusi
        $openInvestments = $investments->where('is_sold', false);
        $closedInvestments = $investments->where('is_sold', true);

        // Statistiche
        $stats = [
            'total_investments' => $investments->count(),
            'open_count' => $openInvestments->count(),
            'closed_count' => $closedInvestments->count(),
            'total_invested' => $openInvestments->sum('total_buy_value'),
            'total_realized_profit' => $closedInvestments->sum('net_profit'),
            'total_fees' => $investments->sum('fees'),
            'total_unrealized_profit' => $this->investmentMetricsService->sumUnrealizedProfit(
                $rawInvestments->filter(fn ($inv) => $inv->isOpen()),
                $currentPrices,
            ),
            'has_price_data' => count($currentPrices) > 0,
        ];

        return Inertia::render('Investments/Index', [
            'investments' => $investments,
            'openInvestments' => $openInvestments->values(),
            'closedInvestments' => $closedInvestments->values(),
            'stats' => $stats,
            'assetTypes' => InvestmentAsset::TYPES,
            'assetTypeIcons' => InvestmentAsset::TYPE_ICONS,
            'valuationNote' => 'I valori di mercato si basano sui prezzi correnti. Patrimonio e allocazione usano il costo di carico (inclusive commissioni).',
        ]);
    }

    /**
     * Mostra il form per creare un nuovo investimento.
     */
    public function create(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $assets = InvestmentAsset::with('currency:code,symbol')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn ($asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'symbol' => $asset->symbol,
                'type' => $asset->type,
                'type_label' => $asset->type_label,
                'type_icon' => $asset->type_icon,
                'currency' => [
                    'code' => $asset->currency->code ?? $asset->currency_code,
                    'symbol' => $asset->currency->symbol ?? '€',
                ],
            ]);

        return Inertia::render('Investments/Create', [
            'accounts' => $accounts,
            'assets' => $assets,
            'assetTypes' => InvestmentAsset::TYPES,
        ]);
    }

    /**
     * Salva un nuovo investimento.
     */
    public function store(StoreInvestmentRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Verifica account appartiene alla household
        if ($validated['account_id']) {
            $account = Account::findOrFail($validated['account_id']);
            if ($account->household_id !== $user->active_household_id) {
                abort(403, 'Il conto non appartiene alla household attiva.');
            }
        }

        $investment = Investment::create([
            'user_id' => $user->id,
            'household_id' => $user->active_household_id,
            'account_id' => $validated['account_id'] ?? null,
            'asset_id' => $validated['asset_id'],
            'quantity' => $validated['quantity'],
            'buy_price' => $validated['buy_price'],
            'nav_at_buy' => $validated['buy_price'],
            'buy_date' => $validated['buy_date'],
            'fees' => $validated['fees'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_private' => $validated['is_private'] ?? false,
        ]);

        $this->investmentTransactionSyncService->syncPurchase($investment);

        return redirect()
            ->route('investments.index')
            ->with('success', 'Investimento registrato con successo.');
    }

    /**
     * Mostra i dettagli di un investimento.
     */
    public function show(Investment $investment): Response
    {
        $this->authorizeInvestment($investment);

        $investment->load(['user:id,name', 'account:id,name', 'asset.currency:code,symbol']);

        $currentPrice = null;
        $symbol = $investment->asset?->symbol;
        if ($symbol && $investment->isOpen()) {
            $priceResult = $this->assetPriceService->getCurrentPrice($symbol);
            if (! $priceResult['error'] && isset($priceResult['price'])) {
                $currentPrice = (float) $priceResult['price'];
            }
        }
        $unrealized = $this->investmentMetricsService->unrealizedMetrics($investment, $currentPrice);

        return Inertia::render('Investments/Show', [
            'investment' => [
                'id' => $investment->id,
                'asset' => [
                    'id' => $investment->asset->id,
                    'name' => $investment->asset->name,
                    'symbol' => $investment->asset->symbol,
                    'type' => $investment->asset->type,
                    'type_label' => $investment->asset->type_label,
                    'type_icon' => $investment->asset->type_icon,
                    'currency' => [
                        'code' => $investment->asset->currency->code ?? $investment->asset->currency_code,
                        'symbol' => $investment->asset->currency->symbol ?? '€',
                    ],
                ],
                'account' => $investment->account ? [
                    'id' => $investment->account->id,
                    'name' => $investment->account->name,
                ] : null,
                'quantity' => (float) $investment->quantity,
                'buy_price' => (float) $investment->buy_price,
                'buy_date' => $investment->buy_date->format('Y-m-d'),
                'sell_price' => $investment->sell_price ? (float) $investment->sell_price : null,
                'sell_date' => $investment->sell_date?->format('Y-m-d'),
                'fees' => $investment->fees ? (float) $investment->fees : null,
                'total_buy_value' => $investment->total_buy_value,
                'total_sell_value' => $investment->total_sell_value,
                'gross_profit' => $investment->gross_profit,
                'net_profit' => $investment->net_profit,
                'profit_percentage' => $investment->profit_percentage !== null
                    ? round($investment->profit_percentage, 2)
                    : null,
                'current_price' => $unrealized['current_price'],
                'current_value' => $unrealized['current_value'],
                'unrealized_profit' => $unrealized['unrealized_profit'],
                'total_cost' => $this->investmentMetricsService->totalCost($investment),
                'is_sold' => $investment->isSold(),
                'is_private' => $investment->is_private,
                'notes' => $investment->notes,
                'created_at' => $investment->created_at->format('d/m/Y H:i'),
                'user' => [
                    'id' => $investment->user->id,
                    'name' => $investment->user->name,
                ],
            ],
        ]);
    }

    /**
     * Mostra il form per modificare un investimento.
     */
    public function edit(Investment $investment): Response
    {
        $this->authorizeInvestment($investment);

        $user = Auth::user();
        $householdId = $user->active_household_id;

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        $assets = InvestmentAsset::with('currency:code,symbol')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn ($asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'symbol' => $asset->symbol,
                'type' => $asset->type,
                'type_label' => $asset->type_label,
                'type_icon' => $asset->type_icon,
                'currency' => [
                    'code' => $asset->currency->code ?? $asset->currency_code,
                    'symbol' => $asset->currency->symbol ?? '€',
                ],
            ]);

        return Inertia::render('Investments/Edit', [
            'investment' => [
                'id' => $investment->id,
                'account_id' => $investment->account_id,
                'asset_id' => $investment->asset_id,
                'quantity' => (float) $investment->quantity,
                'buy_price' => (float) $investment->buy_price,
                'buy_date' => $investment->buy_date->format('Y-m-d'),
                'sell_price' => $investment->sell_price ? (float) $investment->sell_price : null,
                'sell_date' => $investment->sell_date?->format('Y-m-d'),
                'fees' => $investment->fees ? (float) $investment->fees : null,
                'notes' => $investment->notes,
                'is_private' => $investment->is_private,
            ],
            'accounts' => $accounts,
            'assets' => $assets,
            'assetTypes' => InvestmentAsset::TYPES,
        ]);
    }

    /**
     * Aggiorna un investimento esistente.
     */
    public function update(UpdateInvestmentRequest $request, Investment $investment): RedirectResponse
    {
        $this->authorizeInvestment($investment);

        $user = Auth::user();
        $validated = $request->validated();

        // Verifica account appartiene alla household
        if ($validated['account_id']) {
            $account = Account::findOrFail($validated['account_id']);
            if ($account->household_id !== $user->active_household_id) {
                abort(403, 'Il conto non appartiene alla household attiva.');
            }
        }

        $investment->update([
            'account_id' => $validated['account_id'] ?? null,
            'asset_id' => $validated['asset_id'],
            'quantity' => $validated['quantity'],
            'buy_price' => $validated['buy_price'],
            'nav_at_buy' => $validated['buy_price'],
            'buy_date' => $validated['buy_date'],
            'sell_price' => $validated['sell_price'] ?? null,
            'sell_date' => $validated['sell_date'] ?? null,
            'fees' => $validated['fees'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_private' => $validated['is_private'] ?? false,
        ]);

        $this->investmentTransactionSyncService->syncInvestment($investment->fresh());

        return redirect()
            ->route('investments.show', $investment)
            ->with('success', 'Investimento aggiornato con successo.');
    }

    /**
     * Registra la vendita di un investimento.
     */
    public function sell(Investment $investment): RedirectResponse
    {
        $this->authorizeInvestment($investment);

        $validated = request()->validate([
            'sell_price' => ['required', 'numeric', 'min:0'],
            'sell_date' => ['required', 'date'],
            'fees' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Somma le fees esistenti con le nuove
        $totalFees = ((float) $investment->fees ?? 0) + ((float) $validated['fees'] ?? 0);

        $investment->update([
            'sell_price' => $validated['sell_price'],
            'sell_date' => $validated['sell_date'],
            'fees' => $totalFees > 0 ? $totalFees : null,
        ]);

        $this->investmentTransactionSyncService->syncInvestment($investment->fresh());

        $profit = $investment->fresh()->net_profit;
        $message = $profit >= 0
            ? 'Vendita registrata! Profitto netto: '.number_format($profit, 2, ',', '.').' €'
            : 'Vendita registrata. Perdita netta: '.number_format(abs($profit), 2, ',', '.').' €';

        return redirect()
            ->route('investments.show', $investment)
            ->with('success', $message);
    }

    /**
     * Elimina un investimento (soft delete).
     */
    public function destroy(Investment $investment): RedirectResponse
    {
        $this->authorizeInvestment($investment);

        $this->investmentTransactionSyncService->deleteForInvestment($investment);
        $investment->delete();

        return redirect()
            ->route('investments.index')
            ->with('success', 'Investimento eliminato con successo.');
    }

    /**
     * Verifica che l'utente possa accedere all'investimento.
     */
    private function authorizeInvestment(Investment $investment): void
    {
        $user = Auth::user();

        if ($investment->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questo investimento.');
        }

        // Se è privato, solo il proprietario può vederlo
        if ($investment->is_private && $investment->user_id !== $user->id) {
            abort(403, 'Questo investimento è privato.');
        }
    }
}
