<?php

namespace App\Console\Commands;

use App\Mail\ProPlanExpiringMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Invia avvisi agli utenti Pro il cui piano sta per scadere.
 *
 * Invia una email:
 * - 7 giorni prima della scadenza
 * - 1 giorno prima della scadenza
 *
 * Viene eseguito ogni giorno alle 08:00 tramite lo scheduler.
 */
class NotifyExpiringPlans extends Command
{
    protected $signature = 'plans:notify-expiring';
    protected $description = 'Invia email di avviso agli utenti Pro il cui piano scade tra 7 o 1 giorno';

    public function handle(): int
    {
        $notified = 0;

        foreach ([7, 1] as $daysAhead) {
            $targetDate = now()->addDays($daysAhead)->toDateString();

            $users = User::where('plan', 'pro')
                ->whereNotNull('plan_expires_at')
                ->whereDate('plan_expires_at', $targetDate)
                ->get();

            foreach ($users as $user) {
                try {
                    Mail::to($user->email)->send(new ProPlanExpiringMail($user, $daysAhead));
                    $this->info("Avviso {$daysAhead}gg inviato a {$user->email}.");
                    $notified++;
                } catch (\Throwable $e) {
                    report($e);
                    $this->warn("Impossibile inviare email a {$user->email}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Totale notifiche inviate: {$notified}.");

        return self::SUCCESS;
    }
}
