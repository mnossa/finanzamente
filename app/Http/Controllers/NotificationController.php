<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Auth;

/**
 * NotificationController
 *
 * Gestisce le operazioni sulle notifiche in-app dell'utente:
 * elenco, lettura singola e lettura massiva.
 */
class NotificationController extends Controller
{
    /**
     * Marca una notifica come letta.
     */
    public function markRead(AppNotification $notification)
    {
        $this->authorizeNotification($notification);

        $notification->update(['read' => true]);

        return back();
    }

    /**
     * Marca tutte le notifiche non lette dell'utente come lette.
     */
    public function markAllRead()
    {
        AppNotification::where('user_id', Auth::id())
            ->where('read', false)
            ->update(['read' => true]);

        return back();
    }

    // -------------------------------------------------------------------------
    // Autorizzazione
    // -------------------------------------------------------------------------

    private function authorizeNotification(AppNotification $notification): void
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403, 'Non hai accesso a questa notifica.');
        }
    }
}
