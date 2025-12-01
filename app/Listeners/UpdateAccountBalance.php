<?php

namespace App\Listeners;

use App\Events\ModelChanged;
use App\Models\Account;
use App\Models\Transaction;

class UpdateAccountBalance
{
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
        if (! $account) {
            return;
        }

        // Compute signed sum of non-deleted transactions for account.
        // We consider category.type when available (income/expense). If the
        // category type is 'expense' we treat the transaction as negative,
        // if 'income' as positive. If no category is present we fall back to
        // the sign of the amount (negative values are expenses).
        // This also allows supporting transfer flows where two opposite
        // transactions exist (one negative on source, one positive on dest).

        $sumQuery = Transaction::selectRaw(
            "SUM(CASE
                WHEN categories.type = 'expense' THEN -ABS(transactions.amount)
                WHEN categories.type = 'income' THEN ABS(transactions.amount)
                ELSE ABS(transactions.amount)
            END) as signed_sum"
        )
        ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
        ->where('transactions.account_id', $account->id)
        ->whereNull('transactions.deleted_at');

        $row = $sumQuery->first();
        $signedSum = $row->signed_sum ?? 0;

        // current_balance = initial_balance + signed sum of transactions
        $account->current_balance = $account->initial_balance + $signedSum;
        $account->save();
    }
}
