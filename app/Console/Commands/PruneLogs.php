<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneLogs extends Command
{
    protected $signature = 'logs:prune {--days=30 : Rimuove i log più vecchi di questo numero di giorni}';

    protected $description = 'Rimuove i file di log più vecchi del numero di giorni specificato (default: 30)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $logPath = storage_path('logs');
        $cutoff = Carbon::now()->subDays($days);
        $deleted = 0;

        foreach (glob("{$logPath}/*.log") as $file) {
            if (filemtime($file) < $cutoff->timestamp) {
                unlink($file);
                $this->line('Rimosso: '.basename($file));
                $deleted++;
            }
        }

        $this->info($deleted > 0
            ? "Rimossi {$deleted} file di log più vecchi di {$days} giorni."
            : 'Nessun file di log da rimuovere.');

        return self::SUCCESS;
    }
}
