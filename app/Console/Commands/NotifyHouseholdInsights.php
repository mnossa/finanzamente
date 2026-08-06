<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BudgetNotificationService;
use App\Services\ContextualNotificationService;
use App\Services\DashboardPeriodStatsService;
use App\Services\TransactionTrendNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyHouseholdInsights extends Command
{
    protected $signature = 'notifications:household-insights';

    protected $description = 'Notifiche budget, trend e suggerimenti contestuali (job schedulato)';

    public function handle(
        BudgetNotificationService $budgetNotificationService,
        DashboardPeriodStatsService $dashboardPeriodStatsService,
        TransactionTrendNotificationService $trendNotificationService,
        ContextualNotificationService $contextualNotificationService,
    ): int {
        $endOfPeriod = Carbon::today();
        $startOfPeriod = $endOfPeriod->copy()->subDays(29);
        $endOfPrevious = $startOfPeriod->copy()->subDay();
        $startOfPrevious = $endOfPrevious->copy()->subDays(29);

        User::query()
            ->whereNotNull('active_household_id')
            ->chunkById(100, function ($users) use (
                $budgetNotificationService,
                $dashboardPeriodStatsService,
                $trendNotificationService,
                $contextualNotificationService,
                $startOfPeriod,
                $endOfPeriod,
                $startOfPrevious,
                $endOfPrevious,
            ) {
                foreach ($users as $user) {
                    $householdId = (int) $user->active_household_id;
                    $budgetNotificationService->checkAndNotify($user, $householdId);

                    $currentStats = $dashboardPeriodStatsService->calculate($user, $startOfPeriod, $endOfPeriod);
                    $previousStats = $dashboardPeriodStatsService->calculate($user, $startOfPrevious, $endOfPrevious);

                    $trendNotificationService->checkAndNotify(
                        $user,
                        $currentStats,
                        $previousStats,
                        'ultimi 30 giorni',
                        '30 giorni precedenti',
                    );

                    $contextualNotificationService->notifyHousehold($user);
                }
            });

        return self::SUCCESS;
    }
}
