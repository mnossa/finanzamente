<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealVoucherUnitValue extends Model
{
    protected $fillable = [
        'account_id',
        'unit_value',
        'effective_from',
    ];

    protected $casts = [
        'unit_value' => 'decimal:2',
        'effective_from' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
