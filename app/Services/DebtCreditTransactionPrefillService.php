<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\DebtCredit;
use App\Models\RecurringTransaction;
use App\Models\User;

class DebtCreditTransactionPrefillService
{
    /**
     * @return array{
     *   debt_credit_id: string,
     *   transaction_type: 'income'|'expense',
     *   category_id: string,
     *   amount: string,
     *   description: string,
     *   account_id: string,
     *   date: string,
     *   original_currency_code: string,
     *   counterparty: string,
     *   type_label: string,
     * }|null
     */
    public function build(User $user, int $debtCreditId): ?array
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return null;
        }

        $debtCredit = DebtCredit::query()
            ->where('household_id', $householdId)
            ->whereIn('status', ['open', 'overdue'])
            ->with(['transactions' => function ($query) {
                $query->orderByDesc('date')->orderByDesc('created_at')->limit(1);
            }])
            ->find($debtCreditId);

        if ($debtCredit === null) {
            return null;
        }

        $isDebt = $debtCredit->type === 'debt';
        $transactionType = $isDebt ? 'expense' : 'income';
        $categoryId = $this->resolveCategoryId($user, $householdId, $debtCredit, $transactionType);
        $accountId = $this->resolveAccountId($user, $householdId, $debtCredit);
        $amount = number_format(max(0, $debtCredit->getRemainingAmount()), 2, '.', '');
        $description = $this->buildDescription($debtCredit);
        $date = now()->format('Y-m-d');

        return [
            'debt_credit_id' => (string) $debtCredit->id,
            'transaction_type' => $transactionType,
            'category_id' => $categoryId,
            'amount' => $amount,
            'description' => $description,
            'account_id' => $accountId,
            'date' => $date,
            'original_currency_code' => $debtCredit->currency_code,
            'counterparty' => $debtCredit->counterparty,
            'type_label' => $isDebt ? 'Debito' : 'Credito',
        ];
    }

    private function resolveCategoryId(User $user, int $householdId, DebtCredit $debtCredit, string $transactionType): string
    {
        $latestTransaction = $debtCredit->transactions->first();
        if ($latestTransaction?->category_id) {
            return (string) $latestTransaction->category_id;
        }

        $recurring = RecurringTransaction::query()
            ->where('debt_credit_id', $debtCredit->id)
            ->where('active', true)
            ->orderByDesc('updated_at')
            ->first();

        if ($recurring?->category_id) {
            return (string) $recurring->category_id;
        }

        $preferredNames = $transactionType === 'expense'
            ? ['Mutuo', 'Prestiti', 'Debiti', 'Finanziamenti']
            : ['Rimborsi', 'Prestiti', 'Crediti'];

        $category = Category::query()
            ->where('household_id', $householdId)
            ->where('type', $transactionType)
            ->whereIn('name', $preferredNames)
            ->orderBy('name')
            ->first();

        if ($category === null) {
            $category = Category::query()
                ->where('household_id', $householdId)
                ->where('type', $transactionType)
                ->orderBy('name')
                ->first();
        }

        if ($category === null) {
            $category = Category::query()
                ->whereNull('household_id')
                ->where('type', $transactionType)
                ->orderBy('name')
                ->first();
        }

        return $category ? (string) $category->id : '';
    }

    private function resolveAccountId(User $user, int $householdId, DebtCredit $debtCredit): string
    {
        $latestTransaction = $debtCredit->transactions->first();
        if ($latestTransaction?->account_id) {
            return (string) $latestTransaction->account_id;
        }

        $recurring = RecurringTransaction::query()
            ->where('debt_credit_id', $debtCredit->id)
            ->where('active', true)
            ->orderByDesc('updated_at')
            ->first();

        if ($recurring?->account_id) {
            return (string) $recurring->account_id;
        }

        $account = Account::query()
            ->where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->first();

        return $account ? (string) $account->id : '';
    }

    private function buildDescription(DebtCredit $debtCredit): string
    {
        $prefix = $debtCredit->type === 'debt' ? 'Pagamento' : 'Incasso';
        $description = trim("{$prefix} {$debtCredit->counterparty}");

        if ($debtCredit->description) {
            $description .= ' — '.trim((string) $debtCredit->description);
        }

        return $description;
    }
}
