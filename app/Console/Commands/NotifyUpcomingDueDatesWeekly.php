<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UpcomingDueWeeklyNotificationService;
use Illuminate\Console\Command;

class NotifyUpcomingDueDatesWeekly extends Command
{
    protected $signature = 'upcoming-due:notify-weekly';

    protected $description = 'Invia il riepilogo settimanale delle prossime scadenze (ricorrenze e PAC)';

    public function handle(UpcomingDueWeeklyNotificationService $weeklyNotificationService): int
    {
        $count = 0;

        User::query()
            ->whereNotNull('active_household_id')
            ->chunkById(100, function ($users) use ($weeklyNotificationService, &$count) {
                foreach ($users as $user) {
                    if ($weeklyNotificationService->notifyUser($user)) {
                        $count++;
                    }
                }
            });

        $this->info("Riepiloghi settimanali inviati: {$count}");

        return self::SUCCESS;
    }
}
