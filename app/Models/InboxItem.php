<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * InboxItem
 *
 * Voce della Staging Area (Inbox). Raccoglie messaggi/foto inviati via Telegram
 * prima della revisione manuale e della creazione della transazione definitiva.
 *
 * Stati possibili:
 * - draft: appena creato (dati incompleti/non confermati)
 * - needs_review: dati estratti dall'AI, in attesa di revisione utente
 * - confirmed: confermato, transazione creata
 * - rejected: scartato dall'utente
 */
class InboxItem extends Model
{
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'household_id',
        'status',
        'source',
        'type',
        'raw_text',
        'image_path',
        'ai_payload',
        'amount',
        'description',
        'transaction_date',
        'category_id',
        'account_id',
        'transaction_id',
    ];

    protected $casts = [
        'ai_payload' => 'array',
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    // -------------------------------------------------------------------------
    // Relazioni
    // -------------------------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Verifica se la voce è in bozza/da verificare (non ancora confermata).
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['draft', 'needs_review']);
    }

    /**
     * Verifica se mancano dati obbligatori per la conferma.
     */
    public function needsEdit(): bool
    {
        return $this->amount === null;
    }

    /**
     * Scope: solo voci in attesa di revisione.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'needs_review']);
    }

    /**
     * Scope: voci confermate (escluse dai report finché non in confirmed).
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }
}
