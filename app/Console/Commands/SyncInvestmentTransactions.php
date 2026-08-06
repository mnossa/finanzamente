<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Services\InvestmentTransactionSyncService;
use Illuminate\Console\Command;

class SyncInvestmentTransactions extends Command
{
    protected $signature = 'investment-pacs:sync-transactions {--household= : Limita a una household}';

    protected $description = 'Genera o aggiorna le transazioni collegate agli investimenti esistenti';

    public function __construct(private readonly InvestmentTransactionSyncService $syncService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Investment::query()->orderBy('id');

        if ($this->option('household')) {
            $query->where('household_id', (int) $this->option('household'));
        }

        $count = 0;
        $query->chunkById(100, function ($investments) use (&$count) {
            foreach ($investments as $investment) {
                $this->syncService->syncInvestment($investment);
                $count++;
            }
        });

        $this->info("Investimenti sincronizzati: {$count}");

        return self::SUCCESS;
    }
}
