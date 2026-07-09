<?php

namespace App\Http\Requests\Concerns;

use App\Models\Account;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

trait ValidatesExpenseAccountEligibility
{
    protected function validateExpenseAccountEligibility(Validator $validator): void
    {
        $categoryId = $this->input('category_id');
        if (! $categoryId) {
            return;
        }

        $category = Category::query()->find($categoryId);
        if (! $category || $category->type !== 'expense') {
            return;
        }

        $accountIds = $this->expenseAccountIdsToValidate();
        if ($accountIds === []) {
            return;
        }

        $householdId = Auth::user()->active_household_id;
        $hasBlockedAccount = Account::query()
            ->where('household_id', $householdId)
            ->whereIn('id', $accountIds)
            ->get()
            ->contains(fn (Account $account) => $account->isSavingsDeposit());

        if ($hasBlockedAccount) {
            $validator->errors()->add(
                'account_id',
                'I conti deposito non possono essere usati per le uscite.',
            );
        }
    }

    /**
     * @return list<int>
     */
    protected function expenseAccountIdsToValidate(): array
    {
        if ($this->hasSplitPayment()) {
            return collect($this->input('splits', []))
                ->pluck('account_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if (! $this->filled('account_id')) {
            return [];
        }

        return [(int) $this->input('account_id')];
    }

    abstract protected function hasSplitPayment(): bool;
}
