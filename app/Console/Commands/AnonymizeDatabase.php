<?php

namespace App\Console\Commands;

use App\Services\DatabaseAnonymizationService;
use Illuminate\Console\Command;
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

        $this->info('Anonimizzazione completata.');
        foreach ($counts as $table => $count) {
            $this->line(sprintf('  - %s: %d', $table, $count));
        }
        $this->newLine();
        $this->warn('Password di accesso per tutti gli utenti: '.DatabaseAnonymizationService::DEFAULT_PASSWORD);

        return self::SUCCESS;
    }
}
