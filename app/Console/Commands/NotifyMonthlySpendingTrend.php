<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyMonthlySpendingTrend extends Command
{
    protected $signature = 'notifications:monthly-spending';

    protected $description = 'Invia notifica mensile confronto spese mese precedente';

    public function handle(): int
    {
        $today = Carbon::today();
        if (! $today->isLastOfMonth()) {
            return self::SUCCESS;
        }

        $currentStart = $today->copy()->startOfMonth();
        $prevStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $today->copy()->subMonthNoOverflow()->endOfMonth();

        User::query()->whereNotNull('active_household_id')->chunk(100, function ($users) use ($currentStart, $today, $prevStart, $prevEnd) {
            foreach ($users as $user) {
                $prefs = data_get($user->preferences, 'notifications.monthly_spending', ['enabled' => true, 'channels' => ['in_app']]);
                if (! ($prefs['enabled'] ?? true)) {
                    continue;
                }

                $current = abs((float) Transaction::whereHas('account', fn ($q) => $q->where('household_id', $user->active_household_id))
                    ->where('user_id', $user->id)
                    ->where('amount', '<', 0)
                    ->operationalStats()
                    ->whereBetween('date', [$currentStart->toDateString(), $today->toDateString()])
                    ->sum('amount'));
                $previous = abs((float) Transaction::whereHas('account', fn ($q) => $q->where('household_id', $user->active_household_id))
                    ->where('user_id', $user->id)
                    ->where('amount', '<', 0)
                    ->operationalStats()
                    ->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()])
                    ->sum('amount'));

                if ($previous <= 0) {
                    continue;
                }

                $change = (($current - $previous) / $previous) * 100;
                $notificationKey = 'monthly_spending_'.$today->format('Y-m');

                if (AppNotification::where('user_id', $user->id)->where('notification_key', $notificationKey)->exists()) {
                    continue;
                }

                AppNotification::create([
                    'user_id' => $user->id,
                    'title' => '📊 Riepilogo spese mensili',
                    'message' => sprintf(
                        'Nel mese %s hai speso il %.0f%% %s rispetto a %s (%.2f vs %.2f).',
                        $today->locale('it_IT')->translatedFormat('F'),
                        abs($change),
                        $change >= 0 ? 'in più' : 'in meno',
                        $prevStart->locale('it_IT')->translatedFormat('F'),
                        $current,
                        $previous
                    ),
                    'notification_key' => $notificationKey,
                    'read' => false,
                ]);
            }
        });

        return self::SUCCESS;
    }
}
