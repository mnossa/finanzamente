<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use App\Services\HouseholdPermissionService;

class TransferPolicy
{
    public function view(User $user, Transfer $transfer): bool
    {
        $svc = app(HouseholdPermissionService::class);
        $sourceAccount = Account::find($transfer->source_account_id);
        $destAccount = Account::find($transfer->destination_account_id);

        return ($sourceAccount && $svc->isMember($user, $sourceAccount->household_id))
            || ($destAccount && $svc->isMember($user, $destAccount->household_id))
            || ($transfer->user_id && $transfer->user_id === $user->id);
    }

    public function create(User $user, $sourceAccountId): bool
    {
        $svc = app(HouseholdPermissionService::class);
        $account = Account::find($sourceAccountId);
        if (! $account) {
            return false;
        }

        // Guests (view_only) cannot create transfers
        return $svc->canModify($user, $account->household_id);
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        // Only the transfer owner or household manager of source account can delete
        $svc = app(HouseholdPermissionService::class);
        $sourceAccount = Account::find($transfer->source_account_id);
        $householdId = $sourceAccount ? $sourceAccount->household_id : 0;

        // Guests (view_only) cannot delete transfers
        if ($svc->isViewOnly($user, $householdId)) {
            return false;
        }

        return ($transfer->user_id && $transfer->user_id === $user->id)
            || $svc->hasPermission($user, $householdId, 'manage');
    }
}
