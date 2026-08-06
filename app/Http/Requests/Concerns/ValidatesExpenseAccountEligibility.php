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
        if (! $category || ! in_array($category->type, ['expense', 'income'], true)) {
            return;
        }

        $accountIds = $this->expenseAccountIdsToValidate();
        if ($accountIds === []) {
            return;
        }

        $householdId = Auth::user()->active_household_id;
        $accounts = Account::query()
            ->where('household_id', $householdId)
            ->whereIn('id', $accountIds)
            ->get();

        if ($category->type === 'expense' && $accounts->contains(fn (Account $account) => $account->isSavingsDeposit())) {
            $validator->errors()->add(
                'account_id',
                'I conti deposito non possono essere usati per le uscite.',
            );
        }

        if ($accounts->contains(fn (Account $account) => $account->isPensionFund())) {
            $validator->errors()->add(
                'account_id',
                $category->type === 'expense'
                    ? 'I fondi pensione non possono essere usati per le uscite. Usa un trasferimento o aggiorna la posizione.'
                    : 'I fondi pensione non accettano entrate libere. Usa un trasferimento dal conto corrente o aggiorna la posizione.',
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
