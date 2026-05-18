<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesTransactionFilters
{
    /**
     * @return array<int, string>
     */
    protected function transactionFilterKeys(): array
    {
        return ['account_id', 'category_id', 'type', 'from', 'to', 'is_tax_deductible', 'tag_id'];
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

        return $query;
    }
}
