<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DebtCredit
 *
 * Rappresenta prestiti o crediti (debts/credits) associati a una household
 * e a un utente. Tiene traccia di importo, scadenza e stato.
 */
class DebtCredit extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $table = 'debts_credits';

    protected $fillable = [
        'household_id', 'user_id', 'counterparty', 'amount', 'currency_code', 'type', 'due_date', 'status', 'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
