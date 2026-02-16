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
        'user_id', 'account_id', 'category_id', 'amount', 'currency_code', 'date', 'description', 'recurring', 'recurring_transaction_id', 'is_private', 'transfer_id', 'refund_id', 'is_tax_deductible', 'tax_deduction_rate', 'tax_deduction_type', 'tax_year',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'recurring' => 'boolean',
        'is_private' => 'boolean',
        'is_tax_deductible' => 'boolean',
        'tax_deduction_rate' => 'decimal:2',
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

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
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
     * Relazione con il rimborso (se questa transazione è parte di un rimborso).
     */
    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * Rimborsi collegati a questa transazione (se è una spesa rimborsata).
     */
    public function refunds()
    {
        return $this->hasMany(Refund::class, 'original_transaction_id');
    }

    /**
     * Verifica se la transazione è parte di un trasferimento.
     */
    public function isTransfer(): bool
    {
        return $this->transfer_id !== null;
    }

    /**
     * Verifica se la transazione è parte di un rimborso.
     */
    public function isRefund(): bool
    {
        return $this->refund_id !== null;
    }

    /**
     * Verifica se la transazione ha ricevuto dei rimborsi.
     */
    public function hasRefunds(): bool
    {
        return $this->refunds()->exists();
    }

    /**
     * Calcola l'importo totale rimborsato per questa transazione.
     */
    public function getTotalRefundedAmount(): float
    {
        return (float) $this->refunds()
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Calcola l'importo netto (importo originale - rimborsi).
     */
    public function getNetAmount(): float
    {
        $amount = (float) $this->amount;
        $refunded = $this->getTotalRefundedAmount();
        
        // Se è una spesa (negativa), il netto è meno negativo
        if ($amount < 0) {
            return $amount + $refunded;
        }
        
        return $amount - $refunded;
    }

    /**
     * Calcola l'importo detraibile in base alla percentuale.
     */
    public function getTaxDeductibleAmount(): float
    {
        if (!$this->is_tax_deductible || !$this->tax_deduction_rate) {
            return 0.0;
        }

        $baseAmount = abs((float) $this->amount);
        return $baseAmount * ((float) $this->tax_deduction_rate / 100);
    }

    /**
     * Verifica se la transazione è detraibile per l'anno fiscale specificato.
     */
    public function isDeductibleForYear(int $year): bool
    {
        return $this->is_tax_deductible && $this->tax_year === $year;
    }
}
