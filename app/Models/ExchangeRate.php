<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache giornaliera dei tassi di cambio.
 *
 * "1 unità di base_code vale `rate` unità di quote_code alla data `date`".
 * Lo storico è immutabile (audit contabile): una volta scritto un tasso
 * per una data passata, non viene mai più aggiornato.
 */
class ExchangeRate extends Model
{
    protected $table = 'exchange_rates';

    protected $fillable = [
        'base_code',
        'quote_code',
        'date',
        'rate',
        'source',
    ];

    protected $casts = [
        'date' => 'date',
        'rate' => 'decimal:10',
    ];

    public function baseCurrency()
    {
        return $this->belongsTo(Currency::class, 'base_code', 'code');
    }

    public function quoteCurrency()
    {
        return $this->belongsTo(Currency::class, 'quote_code', 'code');
    }
}
