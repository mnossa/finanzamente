<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Refund
 *
 * Rappresenta un rimborso collegato a una transazione di spesa originale.
 * Un rimborso genera una transazione di entrata collegata alla spesa originale,
 * permettendo di tracciare quando e quanto è stato rimborsato.
 */
class Refund extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'original_transaction_id',
        'user_id',
        'amount',
        'currency_code',
        'status',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Transazione originale di spesa che viene rimborsata.
     */
    public function originalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'original_transaction_id');
    }

    /**
     * Transazione di rimborso (entrata) collegata.
     */
    public function refundTransaction()
    {
        return $this->hasOne(Transaction::class, 'refund_id');
    }

    /**
     * Tutte le transazioni collegate al rimborso.
     * Include sia la transazione originale (come riferimento) che quella di rimborso.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'refund_id');
    }

    /**
     * Utente che ha creato il rimborso.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Valuta del rimborso.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * Verifica se il rimborso è completo.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Verifica se il rimborso è annullato.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Calcola l'importo totale rimborsato per la transazione originale.
     */
    public static function getTotalRefundedAmount(int $originalTransactionId): float
    {
        return (float) static::where('original_transaction_id', $originalTransactionId)
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Quando un rimborso viene eliminato (soft delete), elimina anche la transazione collegata.
     */
    protected static function booted()
    {
        static::deleting(function (Refund $refund) {
            if ($refund->refundTransaction) {
                $refund->refundTransaction->delete();
            }
        });
    }
}
