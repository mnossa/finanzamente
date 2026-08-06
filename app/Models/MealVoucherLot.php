<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealVoucherLot extends Model
{
    protected $fillable = [
        'account_id',
        'unit_value',
        'quantity_remaining',
        'acquired_on',
    ];

    protected $casts = [
        'unit_value' => 'decimal:2',
        'quantity_remaining' => 'integer',
        'acquired_on' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MealVoucherLotMovement::class, 'lot_id');
    }
}
