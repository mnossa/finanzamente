<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use App\Services\HouseholdPermissionService;

class AccountPolicy
{
    /** Determine whether the user can view the account. */
    public function view(User $user, Account $account): bool
    {
        $svc = app(HouseholdPermissionService::class);
        if ($account->is_private) {
            return $account->owner_user_id === $user->id || $svc->hasPermission($user, $account->household_id, 'manage');
        }

        return $svc->isMember($user, $account->household_id) || $account->owner_user_id === $user->id;
    }

    public function create(User $user, $householdId): bool
    {
        $svc = app(HouseholdPermissionService::class);

        // Guests (view_only) cannot create accounts
        return $svc->canModify($user, $householdId);
    }

    public function update(User $user, Account $account): bool
    {
        $svc = app(HouseholdPermissionService::class);
        // Guests (view_only) cannot update accounts
        if ($svc->isViewOnly($user, $account->household_id)) {
            return false;
        }

        return $account->owner_user_id === $user->id || $svc->hasPermission($user, $account->household_id, 'manage');
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->update($user, $account);
    }
}
