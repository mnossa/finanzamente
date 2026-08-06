<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * HouseholdInvitation
 *
 * Rappresenta un invito pendente a una household per un utente non ancora registrato.
 * Include un token univoco per identificare l'invito e una data di scadenza.
 *
 * Relazioni principali:
 * - household(): belongsTo(Household)
 * - invitedBy(): belongsTo(User)
 */
class HouseholdInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_id',
        'invited_by_user_id',
        'email',
        'role',
        'token',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Relazione con la household.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Relazione con l'utente che ha creato l'invito.
     */
    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * Genera un token univoco per l'invito.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Verifica se l'invito è ancora valido (non scaduto e non accettato).
     */
    public function isValid(): bool
    {
        return ! $this->accepted_at && $this->expires_at->isFuture();
    }

    /**
     * Verifica se l'invito è scaduto.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Verifica se l'invito è già stato accettato.
     */
    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }

    /**
     * Segna l'invito come accettato.
     */
    public function markAsAccepted(): void
    {
        $this->update(['accepted_at' => now()]);
    }

    /**
     * Scope per trovare inviti validi per una specifica email.
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', strtolower($email));
    }

    /**
     * Scope per trovare inviti non scaduti e non accettati.
     */
    public function scopeValid($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope per trovare un invito tramite token.
     */
    public function scopeByToken($query, string $token)
    {
        return $query->where('token', $token);
    }
}
