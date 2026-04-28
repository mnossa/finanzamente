<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * InvestmentAsset
 *
 * Rappresenta un asset finanziario (crypto, azione, etf, commodity). Gli
 * asset vengono usati per tracciare investimenti e posizioni detenute.
 */
class InvestmentAsset extends Model
{
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    /**
     * Tipi di asset disponibili.
     */
    public const TYPES = [
        'crypto' => 'Criptovaluta',
        'etf' => 'ETF',
        'stock' => 'Azione',
        'index' => 'Indice',
        'commodity' => 'Materia Prima',
        'insurance' => 'Assicurazione',
        'other' => 'Altro',
    ];

    /**
     * Icone per tipo di asset.
     */
    public const TYPE_ICONS = [
        'crypto' => '₿',
        'etf' => '📊',
        'stock' => '📈',
        'index' => '📉',
        'commodity' => '🥇',
        'insurance' => '🛡️',
        'other' => '💼',
    ];

    protected $fillable = [
        'type', 'symbol', 'isin', 'exchange', 'name', 'currency_code', 'extra_data',
    ];

    protected $casts = [
        'extra_data' => 'array',
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
