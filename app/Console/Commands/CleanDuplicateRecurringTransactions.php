<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando per pulire le transazioni ricorrenti duplicate.
 * Utile se il sistema ha creato duplicati prima del fix.
 */
class CleanDuplicateRecurringTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring:clean-duplicates 
                            {--dry-run : Mostra cosa verrebbe eliminato senza eliminare effettivamente}
                            {--force : Forza l\'eliminazione senza conferma}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pulisce le transazioni ricorrenti duplicate mantenendo solo la prima occorrenza';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Ricerca transazioni ricorrenti duplicate...');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Trova i duplicati: stessa recurring_transaction_id e stessa data
        $duplicates = DB::table('transactions')
            ->select('recurring_transaction_id', 'date', DB::raw('COUNT(*) as count'))
            ->whereNotNull('recurring_transaction_id')
            ->whereNull('deleted_at')
            ->groupBy('recurring_transaction_id', 'date')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('✅ Nessun duplicato trovato!');
            return Command::SUCCESS;
        }

        $this->warn("⚠️  Trovati {$duplicates->count()} gruppi di transazioni duplicate");

        $totalToDelete = 0;
        $duplicateDetails = [];

        foreach ($duplicates as $duplicate) {
            $transactions = Transaction::where('recurring_transaction_id', $duplicate->recurring_transaction_id)
                ->whereDate('date', $duplicate->date)
                ->orderBy('id', 'asc')
                ->get();

            // Mantieni solo la prima, segna le altre per eliminazione
            $toDelete = $transactions->slice(1);
            $totalToDelete += $toDelete->count();

            $duplicateDetails[] = [
                'recurring_id' => $duplicate->recurring_transaction_id,
                'date' => $duplicate->date,
                'count' => $duplicate->count,
                'to_delete' => $toDelete->count(),
                'keep_id' => $transactions->first()->id,
                'delete_ids' => $toDelete->pluck('id')->toArray(),
            ];
        }

        // Mostra dettagli
        $this->table(
            ['Ricorrenza ID', 'Data', 'Totale', 'Da Eliminare', 'Mantieni ID', 'Elimina IDs'],
            array_map(function($detail) {
                return [
                    $detail['recurring_id'],
                    $detail['date'],
                    $detail['count'],
                    $detail['to_delete'],
                    $detail['keep_id'],
                    implode(', ', $detail['delete_ids']),
                ];
            }, $duplicateDetails)
        );

        $this->info("\n📊 Riepilogo:");
        $this->info("   - Gruppi duplicati: {$duplicates->count()}");
        $this->info("   - Transazioni totali duplicate: " . ($totalToDelete + $duplicates->count()));
        $this->info("   - Transazioni da eliminare: {$totalToDelete}");
        $this->info("   - Transazioni da mantenere: {$duplicates->count()}");

        if ($dryRun) {
            $this->warn("\n🔍 DRY RUN - Nessuna transazione è stata eliminata");
            return Command::SUCCESS;
        }

        // Chiedi conferma se non in modalità force
        if (!$force && !$this->confirm("\n❓ Vuoi procedere con l'eliminazione?", false)) {
            $this->info('Operazione annullata.');
            return Command::SUCCESS;
        }

        // Elimina i duplicati
        DB::beginTransaction();
        try {
            $deleted = 0;
            foreach ($duplicateDetails as $detail) {
                // Elimina le transazioni duplicate
                Transaction::whereIn('id', $detail['delete_ids'])->delete();
                $deleted += $detail['to_delete'];

                // Aggiorna il saldo dell'account (rimuovi l'importo duplicato)
                foreach ($detail['delete_ids'] as $deleteId) {
                    $transaction = Transaction::withTrashed()->find($deleteId);
                    if ($transaction) {
                        $account = $transaction->account;
                        $account->current_balance -= (float) $transaction->amount;
                        $account->save();
                    }
                }
            }

            DB::commit();

            $this->info("\n✅ Eliminazione completata!");
            $this->info("   - Transazioni eliminate: {$deleted}");
            $this->info("   - Saldi account aggiornati");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n❌ Errore durante l'eliminazione: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
