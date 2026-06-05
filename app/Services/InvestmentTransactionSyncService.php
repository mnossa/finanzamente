<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Investment;
use App\Models\Transaction;

class InvestmentTransactionSyncService
{
    public function syncPurchase(Investment $investment): ?Transaction
    {
        if ($investment->account_id === null) {
            return null;
        }

        $investment->loadMissing(['asset', 'account']);

        $category = $this->resolveInvestmentCategory($investment->household_id);
        $totalCost = (float) $investment->total_buy_value + (float) ($investment->fees ?? 0);
        $description = 'Acquisto investimento - '.($investment->asset?->name ?? 'Asset');

        $transaction = Transaction::query()
            ->where('investment_id', $investment->id)
            ->where('amount', '<', 0)
            ->first();

        $payload = [
            'user_id' => $investment->user_id,
            'account_id' => $investment->account_id,
            'category_id' => $category->id,
            'amount' => -round($totalCost, 2),
            'currency_code' => $investment->account->currency_code,
            'date' => $investment->buy_date,
            'description' => mb_substr($description, 0, 1000),
            'investment_id' => $investment->id,
            'is_private' => $investment->is_private,
        ];

        if ($transaction) {
            $transaction->update($payload);

            return $transaction->fresh();
        }

        return Transaction::create($payload);
    }

    public function syncSale(Investment $investment): ?Transaction
    {
        if ($investment->account_id === null || ! $investment->isSold()) {
            return null;
        }

        $investment->loadMissing(['asset', 'account']);

        $category = $this->resolveInvestmentCategory($investment->household_id);
        $proceeds = (float) $investment->total_sell_value;
        $description = 'Vendita investimento - '.($investment->asset?->name ?? 'Asset');

        $transaction = Transaction::query()
            ->where('investment_id', $investment->id)
            ->where('amount', '>', 0)
            ->first();

        $payload = [
            'user_id' => $investment->user_id,
            'account_id' => $investment->account_id,
            'category_id' => $category->id,
            'amount' => round($proceeds, 2),
            'currency_code' => $investment->account->currency_code,
            'date' => $investment->sell_date,
            'description' => mb_substr($description, 0, 1000),
            'investment_id' => $investment->id,
            'is_private' => $investment->is_private,
        ];

        if ($transaction) {
            $transaction->update($payload);

            return $transaction->fresh();
        }

        return Transaction::create($payload);
    }

    public function deleteForInvestment(Investment $investment): void
    {
        Transaction::query()
            ->where('investment_id', $investment->id)
            ->delete();
    }

    public function syncInvestment(Investment $investment): void
    {
        if ($investment->account_id === null) {
            return;
        }

        $this->syncPurchase($investment);

        if ($investment->isSold()) {
            $this->syncSale($investment);
        }
    }

    private function resolveInvestmentCategory(int $householdId): Category
    {
        $category = Category::firstOrCreate(
            [
                'household_id' => $householdId,
                'name' => 'Investimenti',
                'type' => 'expense',
            ],
            [
                'color' => '#6366f1',
                'icon' => '📈',
                'exclude_from_lifestyle_score' => true,
                'expense_distribution' => 'investments',
            ]
        );

        if ($category->expense_distribution !== 'investments' || ! $category->exclude_from_lifestyle_score) {
            $category->update([
                'exclude_from_lifestyle_score' => true,
                'expense_distribution' => 'investments',
            ]);
        }

        return $category->fresh();
    }
}
