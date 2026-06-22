<?php

namespace App\Services;

use App\Mail\UpcomingDueWeeklyMail;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class UpcomingDueWeeklyNotificationService
{
    public function __construct(
        private readonly UpcomingDueNotificationPreferenceService $preferenceService,
        private readonly UpcomingCashflowService $upcomingCashflowService,
    ) {}

    public function notifyUser(User $user): bool
    {
        if (! $this->preferenceService->isWeekly($user)) {
            return false;
        }

        if ($user->active_household_id === null) {
            return false;
        }

        $movements = $this->upcomingCashflowService->buildUpcomingMovements($user, null, 7);

        if ($movements === []) {
            return false;
        }

        $weekKey = Carbon::today()->format('o-W');
        $notificationKey = "upcoming_due_weekly_{$weekKey}";

        $title = 'Prossime scadenze questa settimana';
        $message = $this->buildMessage($movements);

        if ($this->preferenceService->allowsChannel($user, 'in_app')) {
            $exists = AppNotification::query()
                ->where('user_id', $user->id)
                ->where('notification_key', $notificationKey)
                ->exists();

            if (! $exists) {
                AppNotification::create([
                    'user_id' => $user->id,
                    'title' => $title,
                    'message' => $message,
                    'notification_key' => $notificationKey,
                    'action_url' => route('transactions.index'),
                    'read' => false,
                ]);
            }
        }

        if ($this->preferenceService->allowsChannel($user, 'email') && $user->email) {
            $emailCacheKey = "upcoming_due_weekly_email_{$user->id}_{$notificationKey}";

            if (! Cache::has($emailCacheKey)) {
                Mail::to($user->email)->send(new UpcomingDueWeeklyMail($user, $movements, $message));
                Cache::put($emailCacheKey, true, now()->addDays(8));
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $movements
     */
    private function buildMessage(array $movements): string
    {
        $count = count($movements);
        $lines = array_slice($movements, 0, 5);
        $preview = collect($lines)->map(function (array $movement): string {
            $date = isset($movement['date']) ? Carbon::parse($movement['date'])->format('d/m') : '—';
            $description = (string) ($movement['description'] ?? 'Movimento programmato');

            return "• {$date} — {$description}";
        })->implode("\n");

        $suffix = $count > 5
            ? "\n…e altri ".($count - 5).' movimenti.'
            : '';

        return "Hai {$count} movimenti programmati nei prossimi 7 giorni:\n{$preview}{$suffix}";
    }
}
