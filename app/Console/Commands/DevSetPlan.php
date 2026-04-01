<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Comando di sviluppo: imposta il piano di un utente (Pro o Base).
 *
 * Disponibile solo in ambienti non-production.
 * Utile per testare il ciclo di vita degli abbonamenti senza Mollie.
 *
 * Uso:
 *   php artisan dev:set-plan utente@email.it pro
 *   php artisan dev:set-plan utente@email.it base
 */
class DevSetPlan extends Command
{
    protected $signature = 'dev:set-plan
                            {email : Email dell\'utente}
                            {plan : Piano da impostare (base|pro)}
                            {--expires-in= : Giorni alla scadenza (solo pro, simula grace period)}';

    protected $description = '[Solo sviluppo] Imposta manualmente il piano di un utente';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Questo comando non è disponibile in produzione.');
            return self::FAILURE;
        }

        $email = $this->argument('email');
        $plan = $this->argument('plan');

        if (! in_array($plan, ['base', 'pro'])) {
            $this->error("Piano non valido: '{$plan}'. Scegli tra: base, pro");
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Nessun utente trovato con email: {$email}");
            return self::FAILURE;
        }

        $expiresInDays = $this->option('expires-in');
        $planExpiresAt = null;

        if ($plan === 'pro' && $expiresInDays !== null) {
            $planExpiresAt = now()->addDays((int) $expiresInDays);
        }

        $user->update([
            'plan' => $plan,
            'plan_expires_at' => $planExpiresAt,
        ]);

        $this->info("Piano aggiornato per {$user->email}:");
        $this->table(
            ['Campo', 'Valore'],
            [
                ['plan', $user->fresh()->plan],
                ['plan_expires_at', $planExpiresAt ? $planExpiresAt->format('d/m/Y H:i') . " (tra {$expiresInDays} giorni)" : 'nessuna scadenza'],
                ['isPro()', $user->fresh()->isPro() ? 'true' : 'false'],
            ]
        );

        return self::SUCCESS;
    }
}
