<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    public function view(User $user, Transfer $transfer): bool
    {
        $svc = app(\App\Services\HouseholdPermissionService::class);
        $sourceAccount = \App\Models\Account::find($transfer->source_account_id);
        $destAccount = \App\Models\Account::find($transfer->destination_account_id);
        return ($sourceAccount && $svc->isMember($user, $sourceAccount->household_id))
            || ($destAccount && $svc->isMember($user, $destAccount->household_id))
            || ($transfer->user_id && $transfer->user_id === $user->id);
    }

    public function create(User $user, $sourceAccountId): bool
    {
        $svc = app(\App\Services\HouseholdPermissionService::class);
        $account = \App\Models\Account::find($sourceAccountId);
        if (! $account) {
            return false;
        }

        return $svc->isMember($user, $account->household_id);
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        // Only the transfer owner or household manager of source account can delete
        $svc = app(\App\Services\HouseholdPermissionService::class);
        $sourceAccount = \App\Models\Account::find($transfer->source_account_id);
        $householdId = $sourceAccount ? $sourceAccount->household_id : 0;
        return ($transfer->user_id && $transfer->user_id === $user->id)
            || $svc->hasPermission($user, $householdId, 'manage');
    }
}
