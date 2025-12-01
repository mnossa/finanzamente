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
        'household_id', 'name', 'type', 'color', 'icon',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
