<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionDescriptionFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesTransactionFilters
{
    /**
     * @return array<int, string>
     */
    protected function transactionFilterKeys(): array
    {
        return [
            'account_id',
            'category_id',
            'type',
            'from',
            'to',
            'is_tax_deductible',
            'tag_id',
            'description',
            'description_regex',
            'amount_min',
            'amount_max',
        ];
    }

    protected function applyTransactionFilters(Builder $query, Request $request, int $householdId, User $user): Builder
    {
        $query->whereHas('account', function ($q) use ($householdId) {
            $q->where('household_id', $householdId);
        })->where(function ($q) use ($user) {
            $q->where('is_private', false)
                ->orWhere('user_id', $user->id);
        });

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }
        if ($request->filled('category_id')) {
            if ($request->category_id === '__none__') {
                $query->whereNull('category_id');
            } else {
                $query->where('category_id', $request->category_id);
            }
        }
        if ($request->filled('type')) {
            if ($request->type === 'income') {
                $query->where('amount', '>', 0);
            } elseif ($request->type === 'expense') {
                $query->where('amount', '<', 0);
            }
        }
        if ($request->filled('from')) {
            $query->where('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->to);
        }
        if ($request->filled('is_tax_deductible')) {
            $query->where('is_tax_deductible', $request->is_tax_deductible === 'true');
        }
        if ($request->filled('tag_id')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.id', $request->tag_id);
            });
        }

        TransactionDescriptionFilter::apply(
            $query,
            $request->input('description'),
            $request->boolean('description_regex')
        );

        $amountMin = $request->filled('amount_min') && is_numeric($request->amount_min)
            ? (float) $request->amount_min
            : null;
        $amountMax = $request->filled('amount_max') && is_numeric($request->amount_max)
            ? (float) $request->amount_max
            : null;
        if ($amountMin !== null || $amountMax !== null) {
            $this->applyAbsoluteAmountRangeFilter($query, $amountMin, $amountMax);
        }

        return $query;
    }

    /**
     * Filtra per valore assoluto dell'importo (entrate positive e uscite negative).
     *
     * @param  Builder<Transaction>  $query
     */
    protected function applyAbsoluteAmountRangeFilter(Builder $query, ?float $min, ?float $max): void
    {
        $query->where(function ($q) use ($min, $max) {
            $q->where(function ($positive) use ($min, $max) {
                $positive->where('transactions.amount', '>=', 0);
                if ($min !== null && $min >= 0) {
                    $positive->where('transactions.amount', '>=', $min);
                }
                if ($max !== null && $max >= 0) {
                    $positive->where('transactions.amount', '<=', $max);
                }
            })->orWhere(function ($negative) use ($min, $max) {
                $negative->where('transactions.amount', '<', 0);
                if ($min !== null && $min >= 0) {
                    $negative->where('transactions.amount', '<=', -$min);
                }
                if ($max !== null && $max >= 0) {
                    $negative->where('transactions.amount', '>=', -$max);
                }
            });
        });
    }
}
