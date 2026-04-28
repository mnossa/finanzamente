<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RecurringTransaction
 *
 * Rappresenta una transazione ricorrente (es. affitto mensile). Contiene
 * la frequenza, data di inizio/fine e il riferimento all'account e alla
 * categoria.
 */
class RecurringTransaction extends Model
{
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'account_id',
        'amount',
        'currency_code',
        'frequency',
        'start_date',
        'end_date',
        'description',
        'last_generated_date',
        'debt_credit_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_generated_date' => 'date',
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

    /**
     * Relazione con il debito/credito associato.
     */
    public function debtCredit()
    {
        return $this->belongsTo(DebtCredit::class, 'debt_credit_id');
    }

    /**
     * Verifica se la ricorrenza è associata a un debito/credito.
     */
    public function isDebtPayment(): bool
    {
        return $this->debt_credit_id !== null;
    }
}
