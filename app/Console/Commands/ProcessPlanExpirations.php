<?php

namespace App\Console\Commands;

use App\Mail\ProPlanDowngradedMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Gestisce la scadenza del piano Pro.
 *
 * Cerca gli utenti con piano Pro scaduto (plan = 'pro' e plan_expires_at nel passato)
 * e li degrada a Base. Invia una email di notifica per ciascuno.
 *
 * Viene eseguito ogni giorno alle 00:05 tramite lo scheduler.
 */
class ProcessPlanExpirations extends Command
{
    protected $signature = 'plans:process-expirations';

    protected $description = 'Degrada a Base gli utenti con piano Pro scaduto e invia la email di notifica';

    public function handle(): int
    {
        $expired = User::where('plan', 'pro')
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('Nessun piano Pro scaduto da processare.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $user) {
            $user->update([
                'plan' => 'base',
                'plan_expires_at' => null,
            ]);

            try {
                Mail::to($user->email)->send(new ProPlanDowngradedMail($user));
            } catch (\Throwable $e) {
                report($e);
                $this->warn("Impossibile inviare email di downgrade a {$user->email}: {$e->getMessage()}");
            }

            $this->info("Utente {$user->email} degradato a Base.");
            $count++;
        }

        $this->info("Processati {$count} utenti.");

        return self::SUCCESS;
    }
}
