<?php

namespace App\Listeners;

use App\Events\ModelChanged;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use Illuminate\Support\Carbon;

class UpdateAccountBalance
{
    private static bool $balanceSyncEnabled = true;

    public function __construct(private readonly AccountBalanceService $accountBalanceService) {}

    /**
     * Esegue $callback senza aggiornare i saldi conto (es. import bulk + sync a fine job).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutBalanceSync(callable $callback): mixed
    {
        $previous = self::$balanceSyncEnabled;
        self::$balanceSyncEnabled = false;

        try {
            return $callback();
        } finally {
            self::$balanceSyncEnabled = $previous;
        }
    }

    public function handle(ModelChanged $event): void
    {
        if (! self::$balanceSyncEnabled) {
            return;
        }

        $model = $event->model;

        if (! $model instanceof Transaction) {
            return;
        }

        match ($event->action) {
            'created' => $this->handleCreated($model),
            'deleted' => $this->handleDeleted($model),
            'updated' => $this->handleUpdated($model),
            default => null,
        };
    }

    private function handleCreated(Transaction $transaction): void
    {
        if (! $this->accountBalanceService->affectsStoredBalance($transaction->date)) {
            return;
        }

        $this->applyToAccountId((int) $transaction->account_id, (float) $transaction->amount);
    }

    private function handleDeleted(Transaction $transaction): void
    {
        if (! $this->accountBalanceService->affectsStoredBalance($transaction->date)) {
            return;
        }

        $this->applyToAccountId((int) $transaction->account_id, -(float) $transaction->amount);
    }

    private function handleUpdated(Transaction $transaction): void
    {
        $oldAccountId = (int) ($transaction->getOriginal('account_id') ?? $transaction->account_id);
        $newAccountId = (int) $transaction->account_id;
        $oldAmount = (float) ($transaction->getOriginal('amount') ?? $transaction->amount);
        $newAmount = (float) $transaction->amount;
        $oldDate = $transaction->getOriginal('date') ?? $transaction->date;
        $newDate = $transaction->date;

        $oldAffects = $this->accountBalanceService->affectsStoredBalance(
            $oldDate instanceof Carbon ? $oldDate : Carbon::parse((string) $oldDate)
        );
        $newAffects = $this->accountBalanceService->affectsStoredBalance($newDate);

        if ($oldAccountId === $newAccountId) {
            $delta = ($newAffects ? $newAmount : 0.0) - ($oldAffects ? $oldAmount : 0.0);
            $this->applyToAccountId($newAccountId, $delta);

            return;
        }

        if ($oldAffects) {
            $this->applyToAccountId($oldAccountId, -$oldAmount);
        }

        if ($newAffects) {
            $this->applyToAccountId($newAccountId, $newAmount);
        }
    }

    private function applyToAccountId(int $accountId, float $delta): void
    {
        if ($accountId <= 0) {
            return;
        }

        $account = Account::query()->find($accountId);
        if (! $account instanceof Account) {
            return;
        }

        $this->accountBalanceService->applyDelta($account, $delta);
    }
}
