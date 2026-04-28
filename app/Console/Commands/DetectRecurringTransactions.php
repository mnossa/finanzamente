<?php

namespace App\Console\Commands;

use App\Models\Household;
use App\Services\RecurrenceDetectionService;
use Illuminate\Console\Command;

/**
 * Comando per rilevare pattern ricorrenti nelle transazioni esistenti
 * e creare i relativi suggerimenti in attesa di conferma dall'utente.
 */
class DetectRecurringTransactions extends Command
{
    protected $signature = 'recurring:detect
                            {--household= : ID dell\'household da analizzare (default: tutti)}';

    protected $description = 'Rileva pattern ricorrenti nelle transazioni e genera suggerimenti di ricorrenza';

    public function __construct(private readonly RecurrenceDetectionService $detectionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 Avvio rilevamento ricorrenze...');

        $householdId = $this->option('household');

        if ($householdId) {
            $households = Household::where('id', $householdId)->get();
            if ($households->isEmpty()) {
                $this->error("Household #{$householdId} non trovato.");

                return Command::FAILURE;
            }
        } else {
            $households = Household::all();
        }

        $totalCreated = 0;

        foreach ($households as $household) {
            try {
                $created = $this->detectionService->detectForHousehold($household->id);
                $totalCreated += $created;

                if ($created > 0) {
                    $this->info("  ✅ Household #{$household->id} ({$household->name}): {$created} nuovi suggerimenti");
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Errore household #{$household->id}: {$e->getMessage()}");
            }
        }

        $this->info("✅ Rilevamento completato. Totale suggerimenti creati: {$totalCreated}");

        return Command::SUCCESS;
    }
}
