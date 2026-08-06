<?php

namespace App\Console\Commands;

use App\Mail\RecurringReminderMail;
use App\Models\AppNotification;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Services\RecurringReminderFormatter;
use App\Services\RecurringTransactionService;
use App\Services\UpcomingDueNotificationPreferenceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class RemindRecurringTransactions extends Command
{
    protected $signature = 'recurring:remind';

    protected $description = 'Invia promemoria il giorno prima della scadenza di una transazione ricorrente';

    public function __construct(
        private RecurringTransactionService $recurringService,
        private RecurringReminderFormatter $reminderFormatter,
        private UpcomingDueNotificationPreferenceService $dueNotificationPreferences,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $count = 0;

        RecurringTransaction::query()
            ->with(['account.household.users', 'category'])
            ->chunkById(100, function ($recurringTransactions) use ($tomorrow, &$count) {
                foreach ($recurringTransactions as $recurring) {
                    if (! $this->recurringService->isActive($recurring)) {
                        continue;
                    }

                    $nextDue = $this->recurringService->calculateNextDueDate($recurring);
                    if (! $nextDue || $nextDue->toDateString() !== $tomorrow) {
                        continue;
                    }

                    $household = $recurring->account?->household;
                    if (! $household instanceof Household) {
                        continue;
                    }

                    $this->notifyHouseholdMembers($household, $recurring, $nextDue);
                    $count++;
                }
            });

        $this->info("Promemoria ricorrenze inviati: {$count}");

        return self::SUCCESS;
    }

    private function notifyHouseholdMembers(Household $household, RecurringTransaction $recurring, Carbon $nextDue): void
    {
        $notificationKey = "recurring_remind_{$recurring->id}_{$nextDue->format('Y-m-d')}";
        $details = $this->reminderFormatter->format($recurring, $nextDue);

        foreach ($household->users as $user) {
            if (! $this->dueNotificationPreferences->isDaily($user)) {
                continue;
            }

            $channels = $this->dueNotificationPreferences->channels($user);

            if (in_array('in_app', $channels, true)) {
                $exists = AppNotification::where('user_id', $user->id)
                    ->where('notification_key', $notificationKey)
                    ->exists();

                if (! $exists) {
                    AppNotification::create([
                        'user_id' => $user->id,
                        'title' => $details['title'],
                        'message' => $details['message'],
                        'notification_key' => $notificationKey,
                    ]);
                }
            }

            if (in_array('email', $channels, true) && $user->email) {
                $emailCacheKey = "recurring_remind_email_{$user->id}_{$notificationKey}";
                if (! Cache::has($emailCacheKey)) {
                    Mail::to($user->email)->send(new RecurringReminderMail($user, $recurring, $nextDue));
                    Cache::put($emailCacheKey, true, now()->addHours(48));
                }
            }
        }
    }
}
