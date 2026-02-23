<?php

namespace App\Policies;

use App\Models\BankImportLayout;
use App\Models\User;

class BankImportLayoutPolicy
{
    public function view(User $user, BankImportLayout $layout): bool
    {
        return $layout->user_id === $user->id
            || ($layout->household_id !== null && $layout->household_id === $user->active_household_id);
    }

    public function update(User $user, BankImportLayout $layout): bool
    {
        return $layout->user_id === $user->id;
    }

    public function delete(User $user, BankImportLayout $layout): bool
    {
        return $layout->user_id === $user->id;
    }
}
