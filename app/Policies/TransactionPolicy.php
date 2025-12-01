<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        $svc = app(\App\Services\HouseholdPermissionService::class);
        // private transactions: only owner or household manager
        if ($transaction->is_private) {
            return $transaction->user_id === $user->id || $svc->hasPermission($user, $transaction->account->household_id, 'manage');
        }

        return $svc->isMember($user, $transaction->account->household_id) || $transaction->user_id === $user->id;
    }

    public function create(User $user, $accountId): bool
    {
        $svc = app(\App\Services\HouseholdPermissionService::class);
        // must be member of the account household
        $account = \App\Models\Account::find($accountId);
        if (! $account) {
            return false;
        }

        return $svc->isMember($user, $account->household_id);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        $svc = app(\App\Services\HouseholdPermissionService::class);
        return $transaction->user_id === $user->id || $svc->hasPermission($user, $transaction->account->household_id, 'manage');
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
