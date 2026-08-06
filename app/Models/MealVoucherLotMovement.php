<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealVoucherLotMovement extends Model
{
    protected $fillable = [
        'lot_id',
        'transaction_id',
        'quantity_delta',
        'occurred_on',
        'note',
    ];

    protected $casts = [
        'quantity_delta' => 'integer',
        'occurred_on' => 'date',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MealVoucherLot::class, 'lot_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
