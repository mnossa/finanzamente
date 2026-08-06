<?php

namespace App\Console\Commands;

use App\Console\Concerns\RequiresLocalEnvironment;
use App\Models\TransactionImport;
use Illuminate\Console\Command;

class MarkStaleTransactionImports extends Command
{
    use RequiresLocalEnvironment;

    protected $signature = 'transaction-imports:mark-stale
        {--processing-minutes=120 : Segna come falliti gli import in «processing» avviati da più di X minuti fa}
        {--pending-hours=3 : Segna come falliti gli import ancora in «pending» da più di X ore (coda non eseguita)}
        {--scheduled : Uso interno scheduler (consentito anche in production/staging)}';

    protected $description = 'Segna come falliti gli import bloccati in pending/processing (scheduler o solo local/development).';

    public function handle(): int
    {
        if (! $this->option('scheduled') && ! $this->blockUnlessLocalEnvironment()) {
            return self::FAILURE;
        }

        $processingMinutes = max(1, (int) $this->option('processing-minutes'));
        $pendingHours = max(1, (int) $this->option('pending-hours'));
        $message = 'Importazione interrotta o scaduta (pulizia automatica). Riprova.';

        $processingCutoff = now()->subMinutes($processingMinutes);
        $pendingCutoff = now()->subHours($pendingHours);

        $processingUpdated = TransactionImport::query()
            ->where('status', 'processing')
            ->where(function ($q) use ($processingCutoff) {
                $q->where('started_at', '<', $processingCutoff)
                    ->orWhere(function ($q2) use ($processingCutoff) {
                        $q2->whereNull('started_at')
                            ->where('updated_at', '<', $processingCutoff);
                    });
            })
            ->update([
                'status' => 'failed',
                'error_message' => $message,
                'completed_at' => now(),
            ]);

        $pendingUpdated = TransactionImport::query()
            ->where('status', 'pending')
            ->whereNull('started_at')
            ->where('created_at', '<', $pendingCutoff)
            ->update([
                'status' => 'failed',
                'error_message' => $message,
                'completed_at' => now(),
            ]);

        $this->info("Import segnati come falliti: {$processingUpdated} in processing, {$pendingUpdated} in pending.");

        return self::SUCCESS;
    }
}
