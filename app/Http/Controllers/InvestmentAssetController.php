<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentAssetRequest;
use App\Http\Requests\UpdateInvestmentAssetRequest;
use App\Models\Currency;
use App\Models\InvestmentAsset;
use App\Services\AssetClassificationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentAssetController extends Controller
{
    /**
     * Mostra l'elenco degli asset disponibili.
     */
    public function index(): Response
    {
        $assets = InvestmentAsset::with('currency:code,symbol')
            ->withCount('investments')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'type' => $asset->type,
                    'type_label' => $asset->type_label,
                    'type_icon' => $asset->type_icon,
                    'symbol' => $asset->symbol,
                    'name' => $asset->name,
                    'currency' => [
                        'code' => $asset->currency->code ?? $asset->currency_code,
                        'symbol' => $asset->currency->symbol ?? '€',
                    ],
                    'investments_count' => $asset->investments_count,
                ];
            });

        // Raggruppa per tipo nell'ordine UX definito in TYPES
        $groupedAssets = collect(InvestmentAsset::TYPES)
            ->mapWithKeys(fn ($label, $type) => [$type => $assets->where('type', $type)->values()])
            ->filter(fn ($items) => $items->isNotEmpty());

        // Statistiche
        $stats = [
            'total_assets' => $assets->count(),
            'by_type' => collect(InvestmentAsset::TYPES)->map(function ($label, $type) use ($assets) {
                return [
                    'label' => $label,
                    'icon' => InvestmentAsset::TYPE_ICONS[$type],
                    'count' => $assets->where('type', $type)->count(),
                ];
            })->filter(fn ($item) => $item['count'] > 0)->values(),
        ];

        return Inertia::render('InvestmentAssets/Index', [
            'assets' => $assets,
            'groupedAssets' => $groupedAssets,
            'stats' => $stats,
            'types' => InvestmentAsset::TYPES,
            'typeIcons' => InvestmentAsset::TYPE_ICONS,
        ]);
    }

    /**
     * Mostra il form per creare un nuovo asset.
     */
    public function create(): Response
    {
        $currencies = Currency::orderBy('code')->get(['code', 'name', 'symbol']);

        return Inertia::render('InvestmentAssets/Create', [
            'currencies' => $currencies,
            'types' => InvestmentAsset::TYPES,
            'typeIcons' => InvestmentAsset::TYPE_ICONS,
            'allocationClasses' => AssetClassificationService::CLASS_LABELS,
            'incomePolicies' => InvestmentAsset::INCOME_POLICIES,
        ]);
    }

    /**
     * Salva un nuovo asset.
     */
    public function store(StoreInvestmentAssetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        InvestmentAsset::create([
            'type' => $validated['type'],
            'allocation_asset_class' => filled($validated['allocation_asset_class'] ?? null)
                ? $validated['allocation_asset_class']
                : null,
            'symbol' => $validated['symbol'] ?? null,
            'isin' => $validated['isin'] ?? null,
            'exchange' => $validated['exchange'] ?? null,
            'name' => $validated['name'],
            'currency_code' => $validated['currency_code'],
            'extra_data' => $validated['extra_data'] ?? null,
            'income_policy' => in_array($validated['type'], ['etf', 'stock', 'bond'], true)
                ? ($validated['income_policy'] ?? null)
                : null,
        ]);

        return redirect()
            ->route('investment-assets.index')
            ->with('success', 'Asset creato con successo.');
    }

    /**
     * Mostra il form per modificare un asset.
     */
    public function edit(InvestmentAsset $investmentAsset): Response
    {
        $currencies = Currency::orderBy('code')->get(['code', 'name', 'symbol']);

        return Inertia::render('InvestmentAssets/Edit', [
            'asset' => [
                'id' => $investmentAsset->id,
                'type' => $investmentAsset->type,
                'allocation_asset_class' => $investmentAsset->allocation_asset_class
                    ?? AssetClassificationService::resolveInvestmentAssetClass($investmentAsset),
                'allocation_asset_class_override' => $investmentAsset->allocation_asset_class,
                'symbol' => $investmentAsset->symbol,
                'isin' => $investmentAsset->isin,
                'exchange' => $investmentAsset->exchange,
                'name' => $investmentAsset->name,
                'currency_code' => $investmentAsset->currency_code,
                'extra_data' => $investmentAsset->extra_data,
                'income_policy' => $investmentAsset->income_policy,
            ],
            'currencies' => $currencies,
            'types' => InvestmentAsset::TYPES,
            'typeIcons' => InvestmentAsset::TYPE_ICONS,
            'allocationClasses' => AssetClassificationService::CLASS_LABELS,
            'incomePolicies' => InvestmentAsset::INCOME_POLICIES,
        ]);
    }

    /**
     * Aggiorna un asset esistente.
     */
    public function update(UpdateInvestmentAssetRequest $request, InvestmentAsset $investmentAsset): RedirectResponse
    {
        $validated = $request->validated();

        $investmentAsset->update([
            'type' => $validated['type'],
            'allocation_asset_class' => filled($validated['allocation_asset_class'] ?? null)
                ? $validated['allocation_asset_class']
                : null,
            'symbol' => $validated['symbol'] ?? null,
            'isin' => $validated['isin'] ?? null,
            'exchange' => $validated['exchange'] ?? null,
            'name' => $validated['name'],
            'currency_code' => $validated['currency_code'],
            'extra_data' => $validated['extra_data'] ?? $investmentAsset->extra_data,
            'income_policy' => in_array($validated['type'], ['etf', 'stock', 'bond'], true)
                ? ($validated['income_policy'] ?? null)
                : null,
        ]);

        return redirect()
            ->route('investment-assets.index')
            ->with('success', 'Asset aggiornato con successo.');
    }

    /**
     * Elimina un asset (soft delete).
     */
    public function destroy(InvestmentAsset $investmentAsset): RedirectResponse
    {
        // Verifica che non ci siano investimenti collegati
        if ($investmentAsset->investments()->exists()) {
            return redirect()
                ->route('investment-assets.index')
                ->with('error', 'Impossibile eliminare l\'asset: ci sono investimenti collegati.');
        }

        $investmentAsset->delete();

        return redirect()
            ->route('investment-assets.index')
            ->with('success', 'Asset eliminato con successo.');
    }
}
