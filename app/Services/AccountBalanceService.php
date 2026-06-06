<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccountBalanceService
{
    /**
     * Saldo conto = saldo iniziale + somma importi transazione (amount già firmato).
     */
    public function computeBalance(Account $account, ?User $viewer = null): float
    {
        $sum = $this->transactionQueryForAccount($account, $viewer)->sum('amount');

        return round((float) $account->initial_balance + (float) $sum, 2);
    }

    /**
     * @param  Collection<int, Account>|null  $accounts
     * @return Collection<int, array<string, mixed>>
     */
    public function mapAccountsWithBalance(Collection $accounts, User $viewer): Collection
    {
        return $accounts->map(function (Account $account) use ($viewer) {
            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency_code' => $account->currency_code,
                'initial_balance' => (float) $account->initial_balance,
                'current_balance' => $this->computeBalance($account, $viewer),
                'is_private' => $account->is_private,
            ];
        });
    }

    public function computeHouseholdTotal(User $user, ?Collection $accounts = null): float
    {
        $accounts ??= Account::query()
            ->where('household_id', $user->active_household_id)
            ->where('active', true)
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('owner_user_id', $user->id))
            ->get();

        return round((float) $this->mapAccountsWithBalance($accounts, $user)->sum('current_balance'), 2);
    }

    public function syncStoredBalance(Account $account, ?User $viewer = null): Account
    {
        $account->current_balance = $this->computeBalance($account, $viewer);
        $account->save();

        return $account;
    }

    private function transactionQueryForAccount(Account $account, ?User $viewer): Builder
    {
        return Transaction::query()
            ->where('account_id', $account->id)
            ->when($viewer, function ($query) use ($viewer) {
                $query->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $viewer->id));
            });
    }
}
