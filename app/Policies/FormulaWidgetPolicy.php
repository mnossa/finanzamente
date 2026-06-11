<?php

namespace App\Policies;

use App\Models\FormulaWidget;
use App\Models\User;

class FormulaWidgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active_household_id !== null;
    }

    public function view(User $user, FormulaWidget $formulaWidget): bool
    {
        return $this->ownsOrPublic($user, $formulaWidget);
    }

    public function create(User $user): bool
    {
        return $user->active_household_id !== null;
    }

    public function update(User $user, FormulaWidget $formulaWidget): bool
    {
        return (int) $formulaWidget->user_id === (int) $user->id
            && ! $formulaWidget->is_official_template;
    }

    public function delete(User $user, FormulaWidget $formulaWidget): bool
    {
        return $this->update($user, $formulaWidget);
    }

    private function ownsOrPublic(User $user, FormulaWidget $formulaWidget): bool
    {
        if ($formulaWidget->is_public || $formulaWidget->is_official_template) {
            return true;
        }

        return (int) $formulaWidget->user_id === (int) $user->id;
    }
}
