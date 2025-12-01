<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tag
 *
 * Etichetta assegnabile a transazioni per categorizzazioni flessibili e
 * filtraggio (es. #viaggio, #lavoro). I tag possono essere globali o
 * specifici per household.
 */
class Tag extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $fillable = [
        'household_id', 'name', 'color',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'transaction_tag');
    }
}
