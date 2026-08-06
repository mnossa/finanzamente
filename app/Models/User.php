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
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes;

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

    public function financialVariables()
    {
        return $this->hasMany(FinancialVariable::class);
    }

    public function formulaWidgets()
    {
        return $this->hasMany(FormulaWidget::class);
    }

    public function consents()
    {
        return $this->hasMany(Consent::class);
    }

    public function consentEvents()
    {
        return $this->hasMany(ConsentEvent::class);
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
        'default_currency_code',
        'user_type',
        'fiscal_code',
        'vat_number',
        'profile_completed',
        'profile_settings',
        'income_band',
        'macro_region',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'preferences' => 'array',
            'profile_completed' => 'boolean',
            'profile_settings' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
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
