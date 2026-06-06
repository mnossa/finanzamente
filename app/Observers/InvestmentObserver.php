<?php

namespace App\Observers;

use App\Models\Investment;
use App\Services\InvestmentTransactionSyncService;

class InvestmentObserver
{
    public function __construct(private readonly InvestmentTransactionSyncService $syncService) {}

    public function saved(Investment $investment): void
    {
        if ($investment->account_id === null) {
            return;
        }

        $this->syncService->syncInvestment($investment->fresh());
    }

    public function deleted(Investment $investment): void
    {
        $this->syncService->deleteForInvestment($investment);
    }
}
