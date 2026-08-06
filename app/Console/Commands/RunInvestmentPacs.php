<?php

namespace App\Console\Commands;

use App\Services\InvestmentPacService;
use Illuminate\Console\Command;

class RunInvestmentPacs extends Command
{
    protected $signature = 'investment-pacs:run';

    protected $description = 'Esegue i PAC mensili attivi e genera investimenti';

    public function __construct(private readonly InvestmentPacService $investmentPacService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->investmentPacService->runDuePacs();

        $this->info("PAC eseguiti: {$count}");

        return self::SUCCESS;
    }
}
