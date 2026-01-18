<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * InterHouseholdTransfer
 *
 * Rappresenta un trasferimento di fondi tra account appartenenti a households diverse.
 * Richiede approvazione dalla household destinataria per garantire sicurezza e tracciabilità.
 * 
 * Stati:
 * - pending: In attesa di approvazione dalla household destinataria
 * - approved: Approvato, transazioni create
 * - rejected: Rifiutato dalla household destinataria
 * - cancelled: Annullato dalla household sorgente
 * - completed: Completato (legacy, stesso di approved)
 */
class InterHouseholdTransfer extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $fillable = [
        'uuid',
        'source_household_id',
        'source_account_id',
        'source_user_id',
        'dest_household_id',
        'dest_account_id',
        'dest_user_id',
        'source_amount',
        'source_currency',
        'dest_amount',
        'dest_currency',
        'exchange_rate',
        'fee',
        'description',
        'notes',
        'transfer_date',
        'status',
        'source_transaction_id',
        'dest_transaction_id',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
    ];

    protected $casts = [
        'source_amount' => 'decimal:2',
        'dest_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:12',
        'fee' => 'decimal:2',
        'transfer_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (InterHouseholdTransfer $transfer) {
            if (empty($transfer->uuid)) {
                $transfer->uuid = (string) Str::uuid();
            }
        });

        // Quando un trasferimento viene soft-deleted, eliminiamo anche le transazioni collegate
        static::deleting(function (InterHouseholdTransfer $transfer) {
            if ($transfer->source_transaction_id) {
                Transaction::find($transfer->source_transaction_id)?->delete();
            }
            if ($transfer->dest_transaction_id) {
                Transaction::find($transfer->dest_transaction_id)?->delete();
            }
        });
    }

    // Relazioni
    
    public function sourceHousehold()
    {
        return $this->belongsTo(Household::class, 'source_household_id');
    }

    public function destinationHousehold()
    {
        return $this->belongsTo(Household::class, 'dest_household_id');
    }

    public function sourceAccount()
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function destinationAccount()
    {
        return $this->belongsTo(Account::class, 'dest_account_id');
    }

    public function sourceUser()
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function destinationUser()
    {
        return $this->belongsTo(User::class, 'dest_user_id');
    }

    public function sourceTransaction()
    {
        return $this->belongsTo(Transaction::class, 'source_transaction_id');
    }

    public function destinationTransaction()
    {
        return $this->belongsTo(Transaction::class, 'dest_transaction_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    // Helper methods
    
    /**
     * Verifica se il trasferimento è in attesa di approvazione
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica se il trasferimento è stato approvato
     */
    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'completed']);
    }

    /**
     * Verifica se il trasferimento è stato rifiutato
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Verifica se il trasferimento è stato annullato
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Verifica se un utente appartiene alla household sorgente
     */
    public function isSourceHouseholdMember(User $user): bool
    {
        return $this->sourceHousehold->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Verifica se un utente appartiene alla household destinataria
     */
    public function isDestinationHouseholdMember(User $user): bool
    {
        return $this->destinationHousehold->users()->where('users.id', $user->id)->exists();
    }

    /**
     * Verifica se un utente può approvare il trasferimento
     */
    public function canBeApprovedBy(User $user): bool
    {
        return $this->isPending() && $this->isDestinationHouseholdMember($user);
    }

    /**
     * Verifica se un utente può rifiutare il trasferimento
     */
    public function canBeRejectedBy(User $user): bool
    {
        return $this->isPending() && $this->isDestinationHouseholdMember($user);
    }

    /**
     * Verifica se un utente può annullare il trasferimento
     */
    public function canBeCancelledBy(User $user): bool
    {
        return $this->isPending() && $this->isSourceHouseholdMember($user);
    }
}
