<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

/**
 * BudgetNotificationService
 *
 * Verifica i budget attivi e crea notifiche in-app quando vengono raggiunte
 * soglie di spesa (80%, 90%, 100% del budget). Ogni soglia viene notificata
 * una sola volta per periodo (identificata da notification_key) per evitare
 * notifiche duplicate.
 *
 * Per aggiungere nuove soglie: aggiungere un elemento all'array LEVELS.
 */
class BudgetNotificationService
{
    /**
     * Soglie di notifica con relativa chiave, titolo e messaggio.
     * - threshold: percentuale minima per attivare la notifica
     * - key: suffisso usato nella notification_key per il deduplication
     */
    private const LEVELS = [
        ['threshold' => 100, 'key' => '100', 'title' => '🚨 Budget superato',        'message' => 'Il budget per "%s" è stato superato: €%.2f spesi su €%.2f disponibili.'],
        ['threshold' => 90,  'key' => '90',  'title' => '⚠️ Budget al 90%%',         'message' => 'Attenzione: hai utilizzato il 90%% del budget per "%s" (€%.2f su €%.2f).'],
        ['threshold' => 80,  'key' => '80',  'title' => '⚠️ Budget all\'80%%',        'message' => 'Hai utilizzato l\'80%% del budget per "%s" (€%.2f su €%.2f). Tieniti d\'occhio!'],
    ];

    /**
     * Verifica i budget attivi per la household e crea notifiche se necessario.
     */
    public function checkAndNotify(User $user, int $householdId): void
    {
        $activeBudgets = Budget::where('household_id', $householdId)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->with(['category'])
            ->get();

        foreach ($activeBudgets as $budget) {
            $spent = Transaction::whereHas('account', function ($q) use ($householdId) {
                $q->where('household_id', $householdId);
            })
                ->where('category_id', $budget->category_id)
                ->whereHas('category', function ($q) {
                    $q->where('type', 'expense');
                })
                ->whereBetween('date', [$budget->period_start, $budget->period_end])
                ->sum('amount');

            $spent = abs((float) $spent);
            $percentage = $budget->amount > 0 ? ($spent / $budget->amount) * 100 : 0;
            $periodKey = Carbon::parse($budget->period_start)->format('Y-m');

            foreach (self::LEVELS as $level) {
                if ($percentage >= $level['threshold']) {
                    $notificationKey = "budget_{$budget->id}_{$level['key']}_{$periodKey}";

                    $alreadyNotified = AppNotification::where('user_id', $user->id)
                        ->where('notification_key', $notificationKey)
                        ->exists();

                    if (! $alreadyNotified) {
                        AppNotification::create([
                            'user_id' => $user->id,
                            'title' => $level['title'],
                            'message' => sprintf(
                                $level['message'],
                                $budget->category->name ?? 'Categoria',
                                $spent,
                                $budget->amount
                            ),
                            'read' => false,
                            'notification_key' => $notificationKey,
                        ]);
                    }

                    // Se una soglia più alta è già raggiunta, non controllare quelle inferiori
                    break;
                }
            }
        }
    }
}
