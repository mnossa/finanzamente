<?php

namespace App\Policies;

use App\Models\FinancialVariable;
use App\Models\User;

class FinancialVariablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active_household_id !== null;
    }

    public function view(User $user, FinancialVariable $financialVariable): bool
    {
        return $this->ownsOrPublic($user, $financialVariable);
    }

    public function create(User $user): bool
    {
        return $user->active_household_id !== null;
    }

    public function update(User $user, FinancialVariable $financialVariable): bool
    {
        return (int) $financialVariable->user_id === (int) $user->id
            && ! $financialVariable->is_official_template;
    }

    public function delete(User $user, FinancialVariable $financialVariable): bool
    {
        return $this->update($user, $financialVariable);
    }

    private function ownsOrPublic(User $user, FinancialVariable $financialVariable): bool
    {
        if ($financialVariable->is_public || $financialVariable->is_official_template) {
            return true;
        }

        return (int) $financialVariable->user_id === (int) $user->id;
    }
}
