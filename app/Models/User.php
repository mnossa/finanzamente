<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
    
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
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
        'user_type',
        'fiscal_code',
        'vat_number',
        'profile_completed',
        'profile_settings',
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
            'preferences' => 'array',
            'profile_completed' => 'boolean',
            'profile_settings' => 'array',
        ];
    }

    /**
     * Invia la notifica di verifica email in italiano.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmail);
    }
}
