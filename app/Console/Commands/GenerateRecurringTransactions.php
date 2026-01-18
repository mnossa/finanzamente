<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Comando per generare le transazioni ricorrenti.
 * Eseguibile manualmente o tramite schedule.
 */
class GenerateRecurringTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring:generate 
                            {--date= : Data fino alla quale generare le transazioni (formato Y-m-d, default: oggi)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera le transazioni ricorrenti fino alla data specificata (default: oggi)';

    /**
     * Service per la gestione delle transazioni ricorrenti.
     */
    protected RecurringTransactionService $recurringService;

    /**
     * Create a new command instance.
     */
    public function __construct(RecurringTransactionService $recurringService)
    {
        parent::__construct();
        $this->recurringService = $recurringService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Avvio generazione transazioni ricorrenti...');

        // Determina la data target
        $targetDate = $this->option('date') 
            ? Carbon::createFromFormat('Y-m-d', $this->option('date'))
            : Carbon::today();

        $this->info("📅 Data target: {$targetDate->format('d/m/Y')}");

        try {
            $result = $this->recurringService->processAllRecurringTransactions($targetDate);

            $this->info("✅ Processo completato:");
            $this->info("   - Transazioni ricorrenti elaborate: {$result['total_recurring']}");
            $this->info("   - Transazioni generate: {$result['total_generated']}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Errore durante la generazione: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
