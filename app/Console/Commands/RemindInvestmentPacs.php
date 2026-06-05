<?php

namespace App\Console\Commands;

use App\Mail\InvestmentPacReminderMail;
use App\Models\AppNotification;
use App\Models\Household;
use App\Models\InvestmentPac;
use App\Services\InvestmentPacReminderFormatter;
use App\Services\InvestmentPacService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class RemindInvestmentPacs extends Command
{
    protected $signature = 'investment-pacs:remind';

    protected $description = 'Invia promemoria il giorno prima della prossima esecuzione di un PAC attivo';

    public function __construct(
        private readonly InvestmentPacService $investmentPacService,
        private readonly InvestmentPacReminderFormatter $formatter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $count = 0;

        InvestmentPac::query()
            ->with(['asset', 'household.users'])
            ->where('status', 'active')
            ->chunkById(100, function ($pacs) use ($tomorrow, &$count) {
                foreach ($pacs as $pac) {
                    $nextDue = $this->investmentPacService->calculateNextExecutionDate($pac);
                    if (! $nextDue || $nextDue->toDateString() !== $tomorrow) {
                        continue;
                    }

                    $household = $pac->household;
                    if (! $household instanceof Household) {
                        continue;
                    }

                    $this->notifyHouseholdMembers($household, $pac, $nextDue);
                    $count++;
                }
            });

        $this->info("Promemoria PAC inviati: {$count}");

        return self::SUCCESS;
    }

    private function notifyHouseholdMembers(Household $household, InvestmentPac $pac, Carbon $nextDue): void
    {
        $notificationKey = "investment_pac_remind_{$pac->id}_{$nextDue->format('Y-m-d')}";
        $details = $this->formatter->format($pac, $nextDue);

        foreach ($household->users as $user) {
            $prefs = is_array($user->preferences) ? $user->preferences : [];
            $notifPrefs = $prefs['notifications']['investment_pac_reminder'] ?? [];
            $enabled = $notifPrefs['enabled'] ?? true;
            if (! $enabled) {
                continue;
            }

            $channels = $notifPrefs['channels'] ?? ['in_app', 'email'];
            if (! is_array($channels)) {
                $channels = ['in_app', 'email'];
            }

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
                $emailCacheKey = "investment_pac_remind_email_{$user->id}_{$notificationKey}";
                if (! Cache::has($emailCacheKey)) {
                    Mail::to($user->email)->send(new InvestmentPacReminderMail($user, $pac, $nextDue));
                    Cache::put($emailCacheKey, true, now()->addHours(48));
                }
            }
        }
    }
}
