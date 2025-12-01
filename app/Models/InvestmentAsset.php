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
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $fillable = [
        'type', 'symbol', 'name', 'currency_code', 'extra_data',
    ];

    protected $casts = [
        'extra_data' => 'array',
    ];

    public function investments()
    {
        return $this->hasMany(Investment::class, 'asset_id');
    }
}
