<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdPermissionService;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        $svc = app(HouseholdPermissionService::class);
        // private transactions: only owner or household manager
        if ($transaction->is_private) {
            return $transaction->user_id === $user->id || $svc->hasPermission($user, $transaction->account->household_id, 'manage');
        }

        return $svc->isMember($user, $transaction->account->household_id) || $transaction->user_id === $user->id;
    }

    public function create(User $user, $accountId): bool
    {
        $svc = app(HouseholdPermissionService::class);
        // must be member of the account household and not view_only
        $account = Account::find($accountId);
        if (! $account) {
            return false;
        }

        // Guests (view_only) cannot create transactions
        return $svc->canModify($user, $account->household_id);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        $svc = app(HouseholdPermissionService::class);
        // Guests (view_only) cannot update transactions
        if ($svc->isViewOnly($user, $transaction->account->household_id)) {
            return false;
        }

        return $transaction->user_id === $user->id || $svc->hasPermission($user, $transaction->account->household_id, 'manage');
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
