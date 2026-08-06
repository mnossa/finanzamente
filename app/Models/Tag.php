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
 * filtraggio (es. #viaggio, #lavoro). Ogni tag appartiene a un utente
 * all'interno della household attiva.
 */
class Tag extends Model
{
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    protected $fillable = [
        'household_id', 'user_id', 'name', 'color',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId, int $householdId)
    {
        return $query->where('household_id', $householdId)->where('user_id', $userId);
    }

    public function transactions()
    {
        return $this->belongsToMany(Transaction::class, 'transaction_tag');
    }

    /**
     * Restituisce un tag esistente con lo stesso nome (case-insensitive) nella stessa household,
     * oppure null se non esiste.
     */
    public static function findByNameForHousehold(string $name, int $householdId, ?int $userId = null): ?self
    {
        $query = self::where('household_id', $householdId)
            ->where('name', strtoupper(trim($name)));

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }
}
