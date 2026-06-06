<?php

namespace App\Listeners;

use App\Events\ModelChanged;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountBalanceService;

class UpdateAccountBalance
{
    public function __construct(private readonly AccountBalanceService $accountBalanceService) {}

    /**
     * Handle the event.
     */
    public function handle(ModelChanged $event): void
    {
        $model = $event->model;

        if (! $model instanceof Transaction) {
            return;
        }

        $account = $model->account()->lockForUpdate()->first();
        if (! $account instanceof Account) {
            return;
        }

        $this->accountBalanceService->syncStoredBalance($account);
    }
}
