<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class FormulaSystemUserService
{
    public function getOrCreate(): User
    {
        $email = config('financial_variables.system_user_email', 'formula-templates@system.internal');

        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Finanzamente Templates',
                'password' => Hash::make(str()->random(64)),
                'email_verified_at' => now(),
                'profile_completed' => true,
            ],
        );
    }
}
