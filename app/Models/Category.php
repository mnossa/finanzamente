<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Category
 *
 * Categoria per transazioni, può essere globale o specifica di una household.
 * Utilizzata per classificare entrate/uscite (type = income|expense).
 */
class Category extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $fillable = [
        'household_id', 'name', 'type', 'color', 'icon', 'is_fixed_expense', 'exclude_from_lifestyle_score',
    ];

    protected $casts = [
        'is_fixed_expense' => 'boolean',
        'exclude_from_lifestyle_score' => 'boolean',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Verifica se la categoria rappresenta una spesa fissa.
     */
    public function isFixedExpense(): bool
    {
        return $this->is_fixed_expense;
    }

    /**
     * Scope per ottenere solo le categorie di spese fisse.
     */
    public function scopeFixedExpenses($query)
    {
        return $query->where('is_fixed_expense', true);
    }

    /**
     * Scope per ottenere le categorie di una household specifica.
     */
    public function scopeForHousehold($query, $householdId)
    {
        return $query->where('household_id', $householdId);
    }
}
