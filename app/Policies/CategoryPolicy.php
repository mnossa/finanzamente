<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Services\HouseholdPermissionService;
use Illuminate\Auth\Access\Response;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->active_household_id !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Category $category): bool
    {
        return $category->household_id === $user->active_household_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->active_household_id === null) {
            return false;
        }
        
        // Guests (view_only) cannot create categories
        $svc = app(HouseholdPermissionService::class);
        return $svc->canModify($user, $user->active_household_id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Category $category): bool
    {
        if ($category->household_id !== $user->active_household_id) {
            return false;
        }
        
        // Guests (view_only) cannot update categories
        $svc = app(HouseholdPermissionService::class);
        return $svc->canModify($user, $category->household_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Category $category): bool
    {
        if ($category->household_id !== $user->active_household_id) {
            return false;
        }
        
        // Guests (view_only) cannot delete categories
        $svc = app(HouseholdPermissionService::class);
        return $svc->canModify($user, $category->household_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Category $category): bool
    {
        if ($category->household_id !== $user->active_household_id) {
            return false;
        }
        
        $svc = app(HouseholdPermissionService::class);
        return $svc->canModify($user, $category->household_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Category $category): bool
    {
        if ($category->household_id !== $user->active_household_id) {
            return false;
        }
        
        $svc = app(HouseholdPermissionService::class);
        return $svc->canModify($user, $category->household_id);
    }
}
