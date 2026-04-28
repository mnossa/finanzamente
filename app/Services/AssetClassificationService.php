<?php

namespace App\Services;

/**
 * AssetClassificationService
 *
 * Centralizza le costanti e la logica di classificazione degli asset
 * per la funzionalità di Asset Allocation. Usato sia dall'AssetAllocationController
 * che dal DashboardController per evitare duplicazioni.
 */
class AssetClassificationService
{
    /** Rischio KIID (1-7) per tipo di asset InvestmentAsset */
    public const ASSET_TYPE_RISK = [
        'etf' => 4,
        'stock' => 6,
        'index' => 4,
        'commodity' => 5,
        'insurance' => 2,
        'crypto' => 7,
        'other' => 3,
    ];

    /** Asset class per tipo di asset InvestmentAsset */
    public const ASSET_TYPE_CLASS = [
        'etf' => 'equities',
        'stock' => 'equities',
        'index' => 'equities',
        'commodity' => 'commodities',
        'insurance' => 'bonds',
        'crypto' => 'crypto',
        'other' => 'other',
    ];

    /** Rischio KIID per tipo di conto */
    public const ACCOUNT_TYPE_RISK = [
        'bank' => 1,
        'cash' => 1,
        'card' => 1,
        'crypto' => 7,
        'other' => 1,
    ];

    /** Asset class per tipo di conto */
    public const ACCOUNT_TYPE_CLASS = [
        'bank' => 'liquidity',
        'cash' => 'liquidity',
        'card' => 'liquidity',
        'crypto' => 'crypto',
        'other' => 'liquidity',
    ];

    /** Etichette in italiano per asset class */
    public const CLASS_LABELS = [
        'equities' => 'Azionario',
        'bonds' => 'Obbligazionario',
        'commodities' => 'Commodities',
        'crypto' => 'Crypto',
        'liquidity' => 'Liquidità',
        'other' => 'Altro',
    ];

    /** Colori per asset class (usati nel Donut chart) */
    public const CLASS_COLORS = [
        'equities' => '#3b82f6', // blue
        'bonds' => '#10b981', // emerald
        'commodities' => '#f59e0b', // amber
        'crypto' => '#8b5cf6', // violet
        'liquidity' => '#06b6d4', // cyan
        'other' => '#94a3b8', // slate
    ];

    /**
     * Converte un indice di rischio numerico in etichetta testuale (stile KIID).
     */
    public static function getRiskLabel(float $index): string
    {
        return match (true) {
            $index <= 1.5 => 'Molto Basso',
            $index <= 2.5 => 'Basso',
            $index <= 3.5 => 'Moderato-Basso',
            $index <= 4.5 => 'Moderato',
            $index <= 5.5 => 'Moderato-Alto',
            $index <= 6.5 => 'Alto',
            default => 'Molto Alto',
        };
    }
}
