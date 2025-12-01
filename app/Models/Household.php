<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Household
 *
 * Rappresenta una "household" (famiglia/gruppo) che contiene utenti,
 * conti, budget e altre entità condivise. La household ha un owner
 * (utente proprietario) e una relazione many-to-many con gli utenti
 * tramite la tabella pivot `household_user` che memorizza ruolo e
 * permessi.
 *
 * Relazioni principali:
 * - owner(): belongsTo(User)
 * - users(): belongsToMany(User) con pivot role/permissions
 * - accounts(): hasMany(Account)
 */
class Household extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $fillable = [
        'name',
        'owner_user_id',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'household_user')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }
}
