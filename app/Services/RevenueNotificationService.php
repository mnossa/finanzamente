<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

/**
 * RevenueNotificationService
 *
 * Verifica il fatturato annuo rispetto alla soglia configurata e crea
 * notifiche in-app quando vengono raggiunti i livelli dell'80%, 90% e 100%.
 * Ogni livello viene notificato una sola volta per anno per evitare spam.
 * Se il fatturato scende sotto l'80% (es. anno successivo) i flag vengono
 * azzerati per permettere nuove notifiche.
 */
class RevenueNotificationService
{
    private const LEVELS = [
        ['threshold' => 100, 'key' => '100', 'title' => '🚨 Soglia fatturato superata', 'message' => 'Il tuo fatturato annuo ha superato il 100%% della soglia configurata (€%.2f su €%.2f). Attenzione al regime forfettario!'],
        ['threshold' => 90,  'key' => '90',  'title' => '⚠️ Soglia fatturato al 90%%',  'message' => 'Il tuo fatturato annuo ha raggiunto il 90%% della soglia configurata (€%.2f su €%.2f). Stai avvicinandoti al limite!'],
        ['threshold' => 80,  'key' => '80',  'title' => '⚠️ Soglia fatturato all\'80%%',  'message' => 'Il tuo fatturato annuo ha raggiunto l\'80%% della soglia configurata (€%.2f su €%.2f). Tieniti d\'occhio!'],
    ];

    /**
     * Verifica i livelli di fatturato e crea notifiche se necessario.
     */
    public function checkAndNotify(User $user, float $annualRevenue, float $threshold): void
    {
        if ($threshold <= 0) {
            return;
        }

        $percentage = ($annualRevenue / $threshold) * 100;
        $settings = $user->profile_settings ?? [];
        $notifiedLevels = $settings['revenue_notified_levels'] ?? [];

        // Azzera i flag se il fatturato è tornato sotto l'80% (nuovo anno o correzione)
        if ($percentage < 80 && !empty($notifiedLevels)) {
            $settings['revenue_notified_levels'] = [];
            $user->update(['profile_settings' => $settings]);
            return;
        }

        $changed = false;
        foreach (self::LEVELS as $level) {
            if ($percentage >= $level['threshold'] && !in_array($level['key'], $notifiedLevels)) {
                AppNotification::create([
                    'user_id' => $user->id,
                    'title'   => $level['title'],
                    'message' => sprintf($level['message'], $annualRevenue, $threshold),
                    'read'    => false,
                ]);
                $notifiedLevels[] = $level['key'];
                $changed = true;
            }
        }

        if ($changed) {
            $settings['revenue_notified_levels'] = $notifiedLevels;
            $user->update(['profile_settings' => $settings]);
        }
    }
}
