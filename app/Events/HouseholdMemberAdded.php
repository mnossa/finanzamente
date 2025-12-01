<?php

namespace App\Events;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HouseholdMemberAdded
{
    use Dispatchable, SerializesModels;

    public Household $household;
    public User $user;
    public ?User $actor;
    public string $role;

    public function __construct(Household $household, User $user, ?User $actor = null, string $role = 'member')
    {
        $this->household = $household;
        $this->user = $user;
        $this->actor = $actor;
        $this->role = $role;
    }
}
