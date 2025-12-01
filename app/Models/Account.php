<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Account
 *
 * Rappresenta un conto finanziario appartenente a una household (es. conto
 * bancario, carta, portafoglio crypto). Ha un saldo iniziale e un saldo
 * corrente che può essere aggiornato aggregando le transazioni.
 *
 * Relazioni principali:
 * - household(): belongsTo(Household)
 * - owner(): belongsTo(User)
 * - transactions(): hasMany(Transaction)
 */
class Account extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    public const TYPES = [
        'bank' => 'Conto Bancario',
        'cash' => 'Contanti',
        'card' => 'Carta',
        'broker' => 'Broker',
        'crypto' => 'Crypto',
        'other' => 'Altro',
    ];

    protected $fillable = [
        'household_id',
        'name',
        'type',
        'initial_balance',
        'current_balance',
        'currency_code',
        'active',
        'is_private',
        'owner_user_id',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'active' => 'boolean',
        'is_private' => 'boolean',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Ricalcola e aggiorna il saldo corrente basato sul saldo iniziale + transazioni.
     */
    public function recalculateBalance(): void
    {
        $transactionsSum = $this->transactions()->sum('amount');
        $this->current_balance = $this->initial_balance + $transactionsSum;
        $this->save();
    }
}
