<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\InvestmentPac;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;

class ContextualNotificationService
{
    public function __construct(
        private readonly NotificationThrottleService $notificationThrottleService,
        private readonly RecurringTransactionService $recurringTransactionService,
    ) {}

    public function notifyHousehold(User $user): void
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return;
        }

        $this->notifyUnlinkedPacs($user, $householdId);
        $this->notifyRecurringDueThisWeek($user, $householdId);
    }

    private function notifyUnlinkedPacs(User $user, int $householdId): void
    {
        $pacs = InvestmentPac::query()
            ->with('asset:id,name')
            ->where('household_id', $householdId)
            ->where('status', 'active')
            ->whereNull('account_id')
            ->get();

        foreach ($pacs as $pac) {
            $notificationKey = "pac_unlinked_{$pac->id}";

            if (! $this->notificationThrottleService->canCreateSuggestion($user, $notificationKey)) {
                continue;
            }

            AppNotification::create([
                'user_id' => $user->id,
                'title' => 'PAC senza conto collegato',
                'message' => sprintf(
                    'Il PAC su %s non è collegato a un conto: non impatterà saldo e transazioni finché non lo associ.',
                    $pac->asset?->name ?? 'strumento',
                ),
                'notification_key' => $notificationKey,
                'read' => false,
            ]);
        }
    }

    private function notifyRecurringDueThisWeek(User $user, int $householdId): void
    {
        $today = Carbon::today();
        $weekEnd = $today->copy()->endOfWeek();
        $dueCount = 0;

        $recurrings = RecurringTransaction::query()
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->get();

        foreach ($recurrings as $recurring) {
            if (! $this->recurringTransactionService->isActive($recurring)) {
                continue;
            }

            $nextDue = $this->recurringTransactionService->calculateNextDueDate($recurring);

            if ($nextDue !== null && $nextDue->betweenIncluded($today, $weekEnd)) {
                $dueCount++;
            }
        }

        if ($dueCount < 3) {
            return;
        }

        $notificationKey = 'recurring_due_week_'.$today->format('o-W');

        if (! $this->notificationThrottleService->canCreateSuggestion($user, $notificationKey)) {
            return;
        }

        AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Ricorrenze in scadenza',
            'message' => sprintf(
                'Hai %d ricorrenze previste questa settimana. Controlla i prossimi movimenti in Transazioni.',
                $dueCount,
            ),
            'notification_key' => $notificationKey,
            'read' => false,
        ]);
    }
}
