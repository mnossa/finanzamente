<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Transaction
 *
 * Rappresenta una singola operazione finanziaria collegata a un account e
 * (opzionalmente) a una categoria, tag e a un'eventuale ricorrenza.
 * Il campo `is_private` determina la visibilità della transazione per
 * altri membri della household.
 */
class Transaction extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $fillable = [
        'user_id', 'account_id', 'category_id', 'amount', 'currency_code', 'date', 'description', 'recurring', 'recurring_transaction_id', 'is_private', 'transfer_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'recurring' => 'boolean',
        'is_private' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'transaction_tag');
    }

    public function recurringTransaction()
    {
        return $this->belongsTo(RecurringTransaction::class, 'recurring_transaction_id');
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Verifica se la transazione è parte di un trasferimento.
     */
    public function isTransfer(): bool
    {
        return $this->transfer_id !== null;
    }
}
