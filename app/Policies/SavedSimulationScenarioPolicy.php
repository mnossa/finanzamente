<?php

namespace App\Policies;

use App\Models\SavedSimulationScenario;
use App\Models\User;
use App\Services\HouseholdPermissionService;

class SavedSimulationScenarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active_household_id !== null;
    }

    public function view(User $user, SavedSimulationScenario $scenario): bool
    {
        return (int) $user->active_household_id === (int) $scenario->household_id
            && app(HouseholdPermissionService::class)->isMember($user, $scenario->household_id);
    }

    public function create(User $user): bool
    {
        if ($user->active_household_id === null) {
            return false;
        }

        return app(HouseholdPermissionService::class)->canModify($user, $user->active_household_id);
    }

    public function update(User $user, SavedSimulationScenario $scenario): bool
    {
        return $this->create($user) && $this->view($user, $scenario);
    }

    public function delete(User $user, SavedSimulationScenario $scenario): bool
    {
        return $this->update($user, $scenario);
    }
}
