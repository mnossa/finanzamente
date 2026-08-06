<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentPac;
use App\Models\Transaction;

class InvestmentTransactionSyncService
{
    public function __construct(
        private readonly InvestmentMetricsService $investmentMetricsService,
    ) {}

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
            ->where(function ($q) {
                $q->where('investment_event', 'purchase')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('investment_event')->where('amount', '<', 0);
                    });
            })
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
            'investment_event' => 'purchase',
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
            ->where(function ($q) {
                $q->where('investment_event', 'sale')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('investment_event')->where('amount', '>', 0);
                    });
            })
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
            'investment_event' => 'sale',
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

    public function syncBuyDateFromTransaction(Transaction $transaction): void
    {
        if ($transaction->investment_id === null || (float) $transaction->amount >= 0) {
            return;
        }

        if ($transaction->investment_event === 'coupon') {
            return;
        }

        $investment = Investment::with('asset')->find($transaction->investment_id);
        if ($investment === null) {
            return;
        }

        $buyDate = $transaction->date->format('Y-m-d');
        $payload = ['buy_date' => $buyDate];

        if ($investment->asset?->symbol !== null) {
            $lot = $this->investmentMetricsService->resolvePurchaseLot(
                (float) $investment->total_buy_value,
                $investment->asset->symbol,
                $buyDate,
            );

            $payload['buy_price'] = $lot['buy_price'];
            $payload['nav_at_buy'] = $lot['nav_at_buy'];
            $payload['quantity'] = $lot['quantity'];
        }

        $investment->update($payload);

        if ($investment->investment_pac_id !== null) {
            $latestBuyDate = Investment::where('investment_pac_id', $investment->investment_pac_id)
                ->max('buy_date');

            InvestmentPac::where('id', $investment->investment_pac_id)
                ->update(['last_executed_at' => $latestBuyDate]);
        }
    }

    public function resolveCouponCategory(int $householdId): Category
    {
        return Category::firstOrCreate(
            [
                'household_id' => $householdId,
                'name' => 'Cedole e dividendi',
                'type' => 'income',
            ],
            [
                'color' => '#10b981',
                'icon' => '💵',
                'exclude_from_lifestyle_score' => false,
            ]
        );
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
