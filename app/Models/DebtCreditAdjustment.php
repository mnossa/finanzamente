<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DebtCreditAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'debt_credit_id',
        'user_id',
        'amount',
        'kind',
        'effective_date',
        'reason',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_date' => 'date',
    ];

    public function debtCredit()
    {
        return $this->belongsTo(DebtCredit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
