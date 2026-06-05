<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentPacRequest;
use App\Http\Requests\UpdateInvestmentPacRequest;
use App\Models\Account;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Services\AssetPriceService;
use App\Services\InvestmentMetricsService;
use App\Services\InvestmentPacService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentPacController extends Controller
{
    public function __construct(
        private readonly InvestmentPacService $investmentPacService,
        private readonly AssetPriceService $assetPriceService,
        private readonly InvestmentMetricsService $investmentMetricsService,
    ) {}

    public function index(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $pacs = InvestmentPac::with(['asset:id,name,symbol,isin', 'account:id,name'])
            ->withCount('investments')
            ->where('household_id', $householdId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (InvestmentPac $pac) => [
                'id' => $pac->id,
                'amount' => (float) $pac->amount,
                'adjust_for_inflation' => (bool) $pac->adjust_for_inflation,
                'inflation_rate_annual' => $pac->inflation_rate_annual !== null ? (float) $pac->inflation_rate_annual : null,
                'currency_code' => $pac->currency_code,
                'frequency' => $pac->frequency,
                'start_date' => $pac->start_date?->format('Y-m-d'),
                'end_date' => $pac->end_date?->format('Y-m-d'),
                'last_executed_at' => $pac->last_executed_at?->format('Y-m-d'),
                'status' => $pac->status,
                'notes' => $pac->notes,
                'investments_count' => (int) $pac->investments_count,
                'asset' => [
                    'id' => $pac->asset->id,
                    'name' => $pac->asset->name,
                    'symbol' => $pac->asset->symbol,
                    'isin' => $pac->asset->isin,
                ],
                'account' => $pac->account ? [
                    'id' => $pac->account->id,
                    'name' => $pac->account->name,
                ] : null,
            ]);

        return Inertia::render('InvestmentPacs/Index', ['pacs' => $pacs]);
    }

    public function show(InvestmentPac $investmentPac): Response
    {
        $this->authorizePac($investmentPac);

        $investmentPac->load([
            'asset:id,name,symbol,isin,currency_code',
            'account:id,name,currency_code',
            'investments' => fn ($query) => $query
                ->with(['asset:id,name,symbol,currency_code', 'account:id,name'])
                ->orderByDesc('buy_date')
                ->orderByDesc('id'),
        ]);

        $assetSymbol = $investmentPac->asset->symbol;
        $currentPrice = null;
        if ($assetSymbol) {
            $priceResult = $this->assetPriceService->getCurrentPrice($assetSymbol);
            if (! $priceResult['error'] && isset($priceResult['price'])) {
                $currentPrice = (float) $priceResult['price'];
            }
        }

        $openInvestments = $investmentPac->investments->filter(fn ($investment) => ! $investment->isSold());
        $closedInvestments = $investmentPac->investments->filter(fn ($investment) => $investment->isSold());

        $investments = $investmentPac->investments->map(function ($investment) use ($currentPrice) {
            $metrics = $this->investmentMetricsService->unrealizedMetrics($investment, $currentPrice);

            return [
                'id' => $investment->id,
                'buy_date' => $investment->buy_date?->format('Y-m-d'),
                'buy_price' => (float) $investment->buy_price,
                'quantity' => (float) $investment->quantity,
                'total_buy_value' => (float) $investment->total_buy_value,
                'sell_date' => $investment->sell_date?->format('Y-m-d'),
                'sell_price' => $investment->sell_price !== null ? (float) $investment->sell_price : null,
                'total_sell_value' => $investment->total_sell_value !== null ? (float) $investment->total_sell_value : null,
                'net_profit' => $investment->net_profit !== null ? (float) $investment->net_profit : null,
                'is_sold' => $investment->isSold(),
                'fees' => $investment->fees !== null ? (float) $investment->fees : null,
                'current_price' => $metrics['current_price'],
                'current_value' => $metrics['current_value'],
                'unrealized_profit' => $metrics['unrealized_profit'],
            ];
        })->values();

        $unrealizedTotal = $currentPrice !== null
            ? $this->investmentMetricsService->sumUnrealizedProfit($openInvestments, [$assetSymbol => $currentPrice])
            : null;

        $stats = [
            'executions_count' => $investmentPac->investments->count(),
            'open_count' => $openInvestments->count(),
            'closed_count' => $closedInvestments->count(),
            'invested_total' => (float) $investmentPac->investments->sum(fn ($investment) => $investment->total_buy_value),
            'realized_total' => (float) $closedInvestments->sum(fn ($investment) => $investment->net_profit ?? 0),
            'unrealized_total' => $unrealizedTotal,
            'current_price' => $currentPrice,
        ];

        return Inertia::render('InvestmentPacs/Show', [
            'pac' => [
                'id' => $investmentPac->id,
                'amount' => (float) $investmentPac->amount,
                'fees' => $investmentPac->fees !== null ? (float) $investmentPac->fees : null,
                'adjust_for_inflation' => (bool) $investmentPac->adjust_for_inflation,
                'inflation_rate_annual' => $investmentPac->inflation_rate_annual !== null ? (float) $investmentPac->inflation_rate_annual : null,
                'currency_code' => $investmentPac->currency_code,
                'frequency' => $investmentPac->frequency,
                'start_date' => $investmentPac->start_date?->format('Y-m-d'),
                'end_date' => $investmentPac->end_date?->format('Y-m-d'),
                'last_executed_at' => $investmentPac->last_executed_at?->format('Y-m-d'),
                'status' => $investmentPac->status,
                'notes' => $investmentPac->notes,
                'asset' => [
                    'id' => $investmentPac->asset->id,
                    'name' => $investmentPac->asset->name,
                    'symbol' => $investmentPac->asset->symbol,
                    'isin' => $investmentPac->asset->isin,
                    'currency_code' => $investmentPac->asset->currency_code,
                ],
                'account' => $investmentPac->account ? [
                    'id' => $investmentPac->account->id,
                    'name' => $investmentPac->account->name,
                    'currency_code' => $investmentPac->account->currency_code,
                ] : null,
            ],
            'investments' => $investments,
            'stats' => $stats,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('InvestmentPacs/Create', [
            ...$this->buildFormOptions(),
        ]);
    }

    public function edit(InvestmentPac $investmentPac): Response
    {
        $this->authorizePac($investmentPac);

        return Inertia::render('InvestmentPacs/Edit', [
            ...$this->buildFormOptions(),
            'pac' => [
                'id' => $investmentPac->id,
                'account_id' => $investmentPac->account_id,
                'investment_asset_id' => $investmentPac->investment_asset_id,
                'amount' => (float) $investmentPac->amount,
                'fees' => $investmentPac->fees !== null ? (float) $investmentPac->fees : null,
                'adjust_for_inflation' => (bool) $investmentPac->adjust_for_inflation,
                'inflation_rate_annual' => $investmentPac->inflation_rate_annual !== null ? (float) $investmentPac->inflation_rate_annual : null,
                'currency_code' => $investmentPac->currency_code,
                'frequency' => $investmentPac->frequency,
                'start_date' => $investmentPac->start_date?->format('Y-m-d'),
                'end_date' => $investmentPac->end_date?->format('Y-m-d'),
                'status' => $investmentPac->status,
                'notes' => $investmentPac->notes,
            ],
        ]);
    }

    public function store(StoreInvestmentPacRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();
        $adjustForInflation = $request->boolean('adjust_for_inflation');

        $pac = InvestmentPac::create([
            ...$validated,
            'adjust_for_inflation' => $adjustForInflation,
            'inflation_rate_annual' => $adjustForInflation ? $validated['inflation_rate_annual'] : null,
            'household_id' => $user->active_household_id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->investmentPacService->backfillPacUntilLastUsefulDate($pac, now());

        return redirect()->route('investment-pacs.index')->with('success', 'PAC creato con successo.');
    }

    public function update(UpdateInvestmentPacRequest $request, InvestmentPac $investmentPac): RedirectResponse
    {
        $this->authorizePac($investmentPac);

        $validated = $request->validated();
        $adjustForInflation = $request->boolean('adjust_for_inflation');

        $investmentPac->update([
            ...$validated,
            'adjust_for_inflation' => $adjustForInflation,
            'inflation_rate_annual' => $adjustForInflation ? $validated['inflation_rate_annual'] : null,
        ]);

        $this->investmentPacService->realignPacMovements($investmentPac, now());
        $this->investmentPacService->backfillPacUntilLastUsefulDate($investmentPac, now());

        return redirect()->route('investment-pacs.show', $investmentPac)->with('success', 'PAC aggiornato con successo.');
    }

    public function destroy(InvestmentPac $investmentPac): RedirectResponse
    {
        $this->authorizePac($investmentPac);

        $investmentPac->delete();

        return redirect()->route('investment-pacs.index')->with('success', 'PAC eliminato con successo.');
    }

    public function toggleStatus(InvestmentPac $investmentPac): RedirectResponse
    {
        $this->authorizePac($investmentPac);

        $newStatus = $investmentPac->status === 'active' ? 'paused' : 'active';
        $investmentPac->update(['status' => $newStatus]);

        return back()->with('success', $newStatus === 'active' ? 'PAC riattivato.' : 'PAC messo in pausa.');
    }

    public function runNow(InvestmentPac $investmentPac): RedirectResponse
    {
        $this->authorizePac($investmentPac);

        if ($investmentPac->status !== 'active') {
            return back()->with('error', 'Attiva prima il PAC per generare un acquisto.');
        }

        $this->investmentPacService->runSinglePac($investmentPac, now(), force: true);

        return back()->with('success', 'Movimento di acquisto PAC generato.');
    }

    private function buildFormOptions(): array
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'currency_code']);

        $assets = InvestmentAsset::orderBy('name')
            ->get(['id', 'name', 'symbol', 'isin', 'currency_code']);

        return [
            'accounts' => $accounts,
            'assets' => $assets,
        ];
    }

    private function authorizePac(InvestmentPac $investmentPac): void
    {
        $user = Auth::user();
        if ($investmentPac->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questo PAC.');
        }
    }
}
