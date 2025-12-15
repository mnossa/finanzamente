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
     * Get the membership row for a user in a household
     */
    public function getMembership(User $user, int $householdId): ?object
    {
        return DB::table('household_user')
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Check if a user has view_only permission (guest role).
     * Users with view_only cannot create, update or delete records.
     */
    public function isViewOnly(User $user, int $householdId): bool
    {
        $row = $this->getMembership($user, $householdId);

        if (! $row) {
            return true; // Non member = no access
        }

        // Owner never has view_only
        if (isset($row->role) && $row->role === 'owner') {
            return false;
        }

        // Check guest role
        if (isset($row->role) && $row->role === 'guest') {
            return true;
        }

        // Check permissions JSON
        if (isset($row->permissions) && $row->permissions !== null) {
            $perms = json_decode($row->permissions, true);
            if (is_array($perms) && isset($perms['view_only']) && $perms['view_only'] === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a user can modify records (create, update, delete) in a household.
     * Returns false for guests/view_only users.
     */
    public function canModify(User $user, int $householdId): bool
    {
        if (! $this->isMember($user, $householdId)) {
            return false;
        }

        return ! $this->isViewOnly($user, $householdId);
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

        // Check both array format and key-value format
        if (in_array($permission, $perms, true)) {
            return true;
        }

        if (isset($perms[$permission]) && $perms[$permission] === true) {
            return true;
        }

        return false;
    }
}
