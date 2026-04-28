<?php

namespace App\Models;

use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * User
     *
     * Rappresenta un utente dell'applicazione. Contiene relazioni verso
     * households, accounts, transazioni e investimenti.
     *
     * Relazioni principali:
     * - households(): belongsToMany(Household)
     * - ownedHouseholds(): hasMany(Household)
     * - activeHousehold(): belongsTo(Household)
     * - accounts(): hasMany(Account)
     */

    // Relations
    public function households()
    {
        return $this->belongsToMany(Household::class, 'household_user')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    public function ownedHouseholds()
    {
        return $this->hasMany(Household::class, 'owner_user_id');
    }

    public function activeHousehold()
    {
        return $this->belongsTo(Household::class, 'active_household_id');
    }

    public function accounts()
    {
        return $this->hasMany(Account::class, 'owner_user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function investments()
    {
        return $this->hasMany(Investment::class);
    }

    public function telegramLinkTokens()
    {
        return $this->hasMany(TelegramLinkToken::class);
    }

    public function inboxItems()
    {
        return $this->hasMany(InboxItem::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Restituisce la sottoscrizione attiva più recente (se presente).
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'pending'])
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Verifica se l'utente ha un piano Pro attivo.
     * Considera anche la data di scadenza: se plan_expires_at è nel passato,
     * il piano è scaduto anche se il campo plan è ancora 'pro'.
     */
    public function isPro(): bool
    {
        if ($this->plan !== 'pro') {
            return false;
        }

        if ($this->plan_expires_at !== null && $this->plan_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Restituisce i giorni rimanenti prima della scadenza del piano Pro.
     * Null se non c'è una scadenza impostata (abbonamento attivo senza cancellazione).
     */
    public function planExpiresInDays(): ?int
    {
        if ($this->plan_expires_at === null) {
            return null;
        }

        $days = (int) now()->diffInDays($this->plan_expires_at, false);

        return $days >= 0 ? $days : 0;
    }

    /**
     * Restituisce il numero di conti in eccesso rispetto al limite Base.
     * Zero se l'utente è Pro o non ha conti in eccesso.
     */
    public function excessAccountsCount(): int
    {
        if ($this->isPro() || ! $this->active_household_id) {
            return 0;
        }

        $maxAccounts = config('plans.base_limits.max_accounts', 3);
        $count = Account::where('household_id', $this->active_household_id)->count();

        return max(0, $count - $maxAccounts);
    }

    /**
     * Restituisce il numero di household in eccesso rispetto al limite Base (1).
     * Zero se l'utente è Pro o non ha household in eccesso.
     */
    public function excessHouseholdsCount(): int
    {
        if ($this->isPro()) {
            return 0;
        }

        $count = $this->households()->count();

        return max(0, $count - config('plans.base_limits.max_households', 1));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'birth_date',
        'status',
        'preferences',
        'active_household_id',
        'telegram_chat_id',
        'user_type',
        'fiscal_code',
        'vat_number',
        'profile_completed',
        'profile_settings',
        'plan',
        'plan_expires_at',
        'mollie_customer_id',
        'is_early_bird',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'plan_expires_at' => 'datetime',
            'preferences' => 'array',
            'profile_completed' => 'boolean',
            'profile_settings' => 'array',
            'is_early_bird' => 'boolean',
        ];
    }

    /**
     * Invia la notifica di verifica email in italiano.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmail);
    }

    /**
     * Invia la notifica di reimpostazione password in italiano.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }
}
