<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    /** Determine whether the user can view the account. */
    public function view(User $user, Account $account): bool
    {
        $svc = app(\App\Services\HouseholdPermissionService::class);
        if ($account->is_private) {
            return $account->owner_user_id === $user->id || $svc->hasPermission($user, $account->household_id, 'manage');
        }

        return $svc->isMember($user, $account->household_id) || $account->owner_user_id === $user->id;
    }

    public function create(User $user, $householdId): bool
    {
        $svc = app(\App\Services\HouseholdPermissionService::class);
        return $svc->hasPermission($user, $householdId, 'manage') || $svc->isMember($user, $householdId);
    }

    public function update(User $user, Account $account): bool
    {
        $svc = app(\App\Services\HouseholdPermissionService::class);
        return $account->owner_user_id === $user->id || $svc->hasPermission($user, $account->household_id, 'manage');
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->update($user, $account);
    }
}
