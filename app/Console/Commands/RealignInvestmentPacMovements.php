<?php

namespace App\Console\Commands;

use App\Models\InvestmentPac;
use App\Services\InvestmentPacService;
use Illuminate\Console\Command;

class RealignInvestmentPacMovements extends Command
{
    protected $signature = 'investment-pacs:realign-movements {--household= : Limita a una household}';

    protected $description = 'Ricalcola quantità e prezzi dei movimenti PAC in base al NAV storico';

    public function __construct(private readonly InvestmentPacService $investmentPacService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = InvestmentPac::query()->orderBy('id');

        if ($this->option('household')) {
            $query->where('household_id', (int) $this->option('household'));
        }

        $updated = 0;
        $query->chunkById(50, function ($pacs) use (&$updated) {
            foreach ($pacs as $pac) {
                $updated += $this->investmentPacService->realignPacMovements($pac);
            }
        });

        $this->info("Movimenti PAC aggiornati: {$updated}");

        return self::SUCCESS;
    }
}
