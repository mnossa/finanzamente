<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\User;

class HouseholdPermissionService
{
    /**
     * Check if a user is member of a household
     */
    public function isMember(?User $user, int $householdId): bool
    {
        if (! $user) {
            return false;
        }

        $row = DB::table('household_user')
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->first();

        return (bool) $row;
    }

    /**
     * Check if a user has a specific permission in a household.
     * Permission can be a role name like 'owner' or an item included in the `permissions` JSON.
     */
    public function hasPermission(User $user, int $householdId, string $permission): bool
    {
        $row = DB::table('household_user')
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->first();

        if (! $row) {
            return false;
        }

        // role 'owner' implies all permissions
        if (isset($row->role) && $row->role === 'owner') {
            return true;
        }

        if (! isset($row->permissions) || $row->permissions === null) {
            return false;
        }

        $perms = json_decode($row->permissions, true);
        if (! is_array($perms)) {
            return false;
        }

        return in_array($permission, $perms, true);
    }
}
