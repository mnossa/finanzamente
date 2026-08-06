<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AppNotification
 *
 * Modello per notifiche in-app generate dalle azioni degli utenti
 * (es. aggiunta/rimozione membri, avvisi budget, ecc.). Non confondere con
 * il sistema di Notification di Laravel; questa tabella è pensata per
 * notifiche persistenti visibili nell'interfaccia utente.
 */
class AppNotification extends Model
{
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    protected $table = 'notifications';

    protected $fillable = [
        'user_id', 'title', 'message', 'read', 'notification_key',
    ];

    protected $casts = [
        'read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
