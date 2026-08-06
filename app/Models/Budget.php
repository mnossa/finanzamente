<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Budget
 *
 * Rappresenta un budget assegnato a una categoria per un intervallo di tempo
 * (period_start, period_end). Usato per il monitoraggio delle spese rispetto
 * agli obiettivi impostati dalla household.
 */
class Budget extends Model
{
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    protected $fillable = [
        'household_id', 'category_id', 'amount', 'currency_code', 'period_start', 'period_end', 'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
