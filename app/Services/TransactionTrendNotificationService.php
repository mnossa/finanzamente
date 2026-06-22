<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Carbon\Carbon;

/**
 * TransactionTrendNotificationService
 *
 * Confronta le statistiche degli ultimi 30 giorni con i 30 giorni precedenti
 * e crea notifiche in-app quando si rilevano variazioni significative
 * (aumento o calo superiore alla soglia configurata).
 *
 * Per aggiungere nuovi trigger: aggiungere elementi all'array TRIGGERS o
 * modificare la soglia DEFAULT_THRESHOLD.
 */
class TransactionTrendNotificationService
{
    /** Soglia percentuale minima per considerare una variazione significativa. */
    private const DEFAULT_THRESHOLD = 20.0;

    /**
     * Trigger di notifica disponibili.
     * - type: 'expense_increase' | 'income_increase' | 'expense_decrease' | 'income_decrease'
     * - threshold: % di variazione minima per attivare la notifica
     */
    private const TRIGGERS = [
        [
            'type' => 'expense_increase',
            'threshold' => self::DEFAULT_THRESHOLD,
            'title' => '📈 Uscite in aumento',
            'message' => 'Le tue uscite di %s sono aumentate del %.0f%% rispetto a %s (€%.2f vs €%.2f).',
        ],
        [
            'type' => 'income_increase',
            'threshold' => self::DEFAULT_THRESHOLD,
            'title' => '📈 Entrate in aumento',
            'message' => 'Le tue entrate di %s sono aumentate del %.0f%% rispetto a %s (€%.2f vs €%.2f).',
        ],
        [
            'type' => 'expense_decrease',
            'threshold' => self::DEFAULT_THRESHOLD,
            'title' => '📉 Uscite in diminuzione',
            'message' => 'Le tue uscite di %s sono diminuite del %.0f%% rispetto a %s (€%.2f vs €%.2f).',
        ],
        [
            'type' => 'income_decrease',
            'threshold' => self::DEFAULT_THRESHOLD,
            'title' => '📉 Entrate in diminuzione',
            'message' => 'Le tue entrate di %s sono diminuite del %.0f%% rispetto a %s (€%.2f vs €%.2f).',
        ],
    ];

    public function __construct(
        private readonly NotificationThrottleService $notificationThrottleService,
    ) {}

    /**
     * Verifica le variazioni di trend su finestra rolling 30 giorni e crea notifiche se necessario.
     *
     * @param  array  $currentStats  ['income' => float, 'expenses' => float, ...]
     * @param  array  $lastMonthStats  ['income' => float, 'expenses' => float, ...]
     */
    public function checkAndNotify(
        User $user,
        array $currentStats,
        array $lastMonthStats,
        string $currentMonth,
        string $lastMonth
    ): void {
        // Non notificare se il mese precedente non ha dati (non confrontabile)
        if ($lastMonthStats['expenses'] <= 0 && $lastMonthStats['income'] <= 0) {
            return;
        }

        $yearMonth = Carbon::now()->format('Y-m');

        foreach (self::TRIGGERS as $trigger) {
            [$current, $previous, $direction] = $this->resolveStats(
                $trigger['type'],
                $currentStats,
                $lastMonthStats
            );

            // Salta se il mese precedente non ha dati per questo tipo
            if ($previous <= 0) {
                continue;
            }

            $change = (($current - $previous) / $previous) * 100;

            $isIncrease = str_ends_with($trigger['type'], '_increase');
            $conditionMet = $isIncrease
                ? $change >= $trigger['threshold']
                : $change <= -$trigger['threshold'];

            if (! $conditionMet) {
                continue;
            }

            $notificationKey = "trend_{$trigger['type']}_{$yearMonth}";

            $alreadyNotified = AppNotification::where('user_id', $user->id)
                ->where('notification_key', $notificationKey)
                ->exists();

            if (! $alreadyNotified && $this->notificationThrottleService->canCreateSuggestion($user, $notificationKey)) {
                AppNotification::create([
                    'user_id' => $user->id,
                    'title' => $trigger['title'],
                    'message' => sprintf(
                        $trigger['message'],
                        $currentMonth,
                        abs($change),
                        $lastMonth,
                        $current,
                        $previous
                    ),
                    'read' => false,
                    'notification_key' => $notificationKey,
                ]);
            }
        }
    }

    /**
     * Restituisce [valore_corrente, valore_precedente, direzione] per il tipo di trigger.
     */
    private function resolveStats(string $type, array $current, array $previous): array
    {
        return match (true) {
            str_starts_with($type, 'expense') => [$current['expenses'], $previous['expenses'], $type],
            str_starts_with($type, 'income') => [$current['income'],   $previous['income'],   $type],
            default => [0, 0, $type],
        };
    }
}
