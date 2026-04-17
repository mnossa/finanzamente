<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * RecurringTransactionSuggestion
 *
 * Rappresenta un suggerimento di ricorrenza rilevato automaticamente
 * dall'analisi delle transazioni esistenti.
 */
class RecurringTransactionSuggestion extends Model
{
    use HasFactory, DispatchesModelEvents;

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'amount',
        'currency_code',
        'description',
        'detected_frequency',
        'confidence',
        'status',
        'transaction_ids',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'confidence' => 'decimal:2',
        'transaction_ids' => 'array',
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
     * Restituisce le transazioni associate al suggerimento.
     */
    public function transactions()
    {
        return Transaction::whereIn('id', $this->transaction_ids ?? []);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isIgnored(): bool
    {
        return $this->status === 'ignored';
    }

    /**
     * Etichetta leggibile del livello di confidenza.
     */
    public function confidenceLabel(): string
    {
        return match (true) {
            $this->confidence >= 0.80 => 'alto',
            $this->confidence >= 0.50 => 'medio',
            default                    => 'basso',
        };
    }

    /** Scope per i suggerimenti in attesa. */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
