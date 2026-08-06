<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DatabaseAnonymizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AnonymizeDatabase extends Command
{
    protected $signature = 'db:anonymize {--force : Salta la conferma interattiva}';

    protected $description = 'Anonimizza i dati personali nel database locale (solo non-production)';

    public function __construct(private readonly DatabaseAnonymizationService $anonymizationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->anonymizationService->assertSafeEnvironment();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Anonimizzare tutti i dati personali nel database corrente?')) {
            $this->info('Operazione annullata.');

            return self::SUCCESS;
        }

        $counts = $this->anonymizationService->run();

        $this->call('cache:clear');

        $this->info('Anonimizzazione completata.');
        foreach ($counts as $table => $count) {
            $this->line(sprintf('  - %s: %d', $table, $count));
        }
        $this->newLine();
        $this->info('Accesso locale su http://localhost:8080/accedi');
        $this->line('  (non usare la porta 8081: è il DB E2E separato)');
        User::withTrashed()->orderBy('id')->each(
            fn (User $user) => $this->line('  - '.$user->email)
        );
        $this->warn('Password per tutti gli utenti: '.DatabaseAnonymizationService::DEFAULT_PASSWORD);
        $this->line('2FA disabilitata su tutti gli utenti (debug locale).');

        $primaryUser = User::withTrashed()->orderBy('id')->first();
        $loginVerified = $primaryUser !== null
            && Auth::attempt([
                'email' => $primaryUser->email,
                'password' => DatabaseAnonymizationService::DEFAULT_PASSWORD,
            ]);
        Auth::logout();

        if ($loginVerified) {
            $this->info('Verifica login: OK');
        } else {
            $this->error('Verifica login: FALLITA — non usare credenziali di produzione, riesegui make db-anonymize dopo l\'ultimo import.');
        }

        return $loginVerified ? self::SUCCESS : self::FAILURE;
    }
}
