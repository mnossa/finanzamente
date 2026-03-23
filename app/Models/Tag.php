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

    /**
     * Normalizza il nome del tag in uppercase prima del salvataggio.
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'transaction_tag');
    }

    /**
     * Restituisce un tag esistente con lo stesso nome (case-insensitive) nella stessa household,
     * oppure null se non esiste.
     */
    public static function findByNameForHousehold(string $name, int $householdId): ?self
    {
        return self::where('household_id', $householdId)
            ->where('name', strtoupper(trim($name)))
            ->first();
    }
}
