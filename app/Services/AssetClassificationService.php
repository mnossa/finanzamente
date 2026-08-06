<?php

namespace App\Services;

use App\Models\InvestmentAsset;

/**
 * AssetClassificationService
 *
 * Centralizza le costanti e la logica di classificazione degli asset
 * per la funzionalità di Asset Allocation. Usato sia dall'AssetAllocationController
 * che dal DashboardController per evitare duplicazioni.
 */
class AssetClassificationService
{
    public const ALLOCATION_CLASSES = [
        'equities',
        'bonds',
        'commodities',
        'crypto',
        'other',
    ];

    /** Rischio KIID (1-7) per tipo di asset InvestmentAsset */
    public const ASSET_TYPE_RISK = [
        'etf' => 4,
        'stock' => 6,
        'bond' => 2,
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
        'bond' => 'bonds',
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
        'pension_fund' => 3,
        'other' => 1,
    ];

    /** Asset class per tipo di conto */
    public const ACCOUNT_TYPE_CLASS = [
        'bank' => 'liquidity',
        'cash' => 'liquidity',
        'card' => 'liquidity',
        'crypto' => 'crypto',
        'pension_fund' => 'locked',
        'meal_voucher' => 'liquidity',
        'other' => 'liquidity',
    ];

    /** Etichette in italiano per asset class */
    public const CLASS_LABELS = [
        'equities' => 'Azionario',
        'bonds' => 'Obbligazionario',
        'locked' => 'Vincolati',
        // Alias legacy (snapshot vecchi / override): stessi colori/label di Vincolati
        'deposit' => 'Vincolati',
        'pension' => 'Vincolati',
        'commodities' => 'Commodities',
        'crypto' => 'Crypto',
        'liquidity' => 'Liquidi',
        'other' => 'Altro',
    ];

    /** Colori per asset class (usati nel Donut chart) */
    public const CLASS_COLORS = [
        'equities' => '#3b82f6', // blue
        'bonds' => '#10b981', // emerald
        'locked' => '#6366f1', // indigo
        'deposit' => '#6366f1',
        'pension' => '#6366f1',
        'commodities' => '#f59e0b', // amber
        'crypto' => '#8b5cf6', // violet
        'liquidity' => '#06b6d4', // cyan
        'other' => '#94a3b8', // slate
    ];

    /**
     * Classe allocazione effettiva per un asset investimento (override manuale o inferenza).
     */
    public static function resolveInvestmentAssetClass(InvestmentAsset $asset): string
    {
        if (
            $asset->allocation_asset_class !== null
            && in_array($asset->allocation_asset_class, self::ALLOCATION_CLASSES, true)
        ) {
            return $asset->allocation_asset_class;
        }

        if ($asset->type === 'etf') {
            return self::inferEtfAllocationClass($asset->name, $asset->symbol, $asset->isin);
        }

        return self::ASSET_TYPE_CLASS[$asset->type] ?? 'other';
    }

    /**
     * Suggerimento automatico per il form asset (da tipo + nome/simbolo).
     */
    public static function suggestAllocationClass(string $type, ?string $name = null, ?string $symbol = null, ?string $isin = null): string
    {
        if ($type === 'etf') {
            return self::inferEtfAllocationClass($name, $symbol, $isin);
        }

        return self::ASSET_TYPE_CLASS[$type] ?? 'other';
    }

    private static function inferEtfAllocationClass(?string $name, ?string $symbol, ?string $isin = null): string
    {
        $haystack = strtolower(trim(($name ?? '').' '.($symbol ?? '').' '.($isin ?? '')));

        if ($haystack === '') {
            return 'equities';
        }

        if (preg_match('/\b(bond|obblig|aggregate|treasury|govt|gilt|eurogov|fixed.?income|titoli.?stato|corporate|high.?yield|emerging.?market.?bond|tips|inflation.?linked)\b/i', $haystack)) {
            return 'bonds';
        }

        if (preg_match('/\b(gold|silver|commodit|materia.?prima|oil|petrolio|wti|brent|platinum|copper|reit|real.?estate)\b/i', $haystack)) {
            return 'commodities';
        }

        if (preg_match('/\b(bitcoin|ethereum|crypto|btc|eth)\b/i', $haystack)) {
            return 'crypto';
        }

        if (preg_match('/\b(balanced|bilanciato|multi.?asset|all.?weather|target.?date|lifecycle)\b/i', $haystack)) {
            return 'other';
        }

        return 'equities';
    }

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

    /**
     * Opzioni override classe allocazione per form asset investimento (no liquidi/vincolati).
     *
     * @return array<string, string>
     */
    public static function investmentAllocationClassLabels(): array
    {
        $labels = [];
        foreach (self::ALLOCATION_CLASSES as $class) {
            $labels[$class] = self::CLASS_LABELS[$class] ?? $class;
        }

        return $labels;
    }
}
