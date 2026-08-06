<?php

namespace App\Events;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HouseholdMemberRemoved
{
    use Dispatchable, SerializesModels;

    public Household $household;

    public User $user;

    public ?User $actor;

    public function __construct(Household $household, User $user, ?User $actor = null)
    {
        $this->household = $household;
        $this->user = $user;
        $this->actor = $actor;
    }
}
