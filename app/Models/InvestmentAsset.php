<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * InvestmentAsset
 *
 * Rappresenta un asset finanziario (ETF, azione, obbligazione, crypto, …).
 */
class InvestmentAsset extends Model
{
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    /**
     * Tipi di asset disponibili (ordine UX: retail IT, crypto in basso, Altro ultimo).
     */
    public const TYPES = [
        'etf' => 'ETF',
        'stock' => 'Azione',
        'bond' => 'Obbligazione',
        'insurance' => 'Assicurazione',
        'index' => 'Indice',
        'commodity' => 'Materia Prima',
        'crypto' => 'Criptovaluta',
        'other' => 'Altro',
    ];

    /**
     * Icone per tipo di asset.
     */
    public const TYPE_ICONS = [
        'etf' => '📊',
        'stock' => '📈',
        'bond' => '🏛️',
        'insurance' => '🛡️',
        'index' => '📉',
        'commodity' => '🥇',
        'crypto' => '₿',
        'other' => '💼',
    ];

    protected $fillable = [
        'type',
        'allocation_asset_class',
        'symbol',
        'isin',
        'exchange',
        'name',
        'currency_code',
        'extra_data',
        'coupon_frequency',
        'next_coupon_date',
        'coupon_rate_percent',
        'coupon_rate_steps',
    ];

    protected $casts = [
        'extra_data' => 'array',
        'next_coupon_date' => 'date',
        'coupon_rate_percent' => 'decimal:4',
        'coupon_rate_steps' => 'array',
    ];

    public function investments()
    {
        return $this->hasMany(Investment::class, 'asset_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * Ottiene l'etichetta del tipo.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Ottiene l'icona del tipo.
     */
    public function getTypeIconAttribute(): string
    {
        return self::TYPE_ICONS[$this->type] ?? '💼';
    }
}
