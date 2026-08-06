<?php

namespace App\Listeners;

use App\Events\HouseholdMemberAdded;
use App\Events\HouseholdMemberRemoved;
use App\Models\AppNotification;

class NotifyHouseholdMembers
{
    public function handle($event): void
    {
        // determine members to notify (all household users except actor)
        $household = $event->household;
        $actorId = $event->actor?->id ?? null;

        $message = '';
        $title = '';

        if ($event instanceof HouseholdMemberAdded) {
            $title = 'Member added to household';
            $message = sprintf('%s was added to household %s with role %s', $event->user->email, $household->name, $event->role);
        } elseif ($event instanceof HouseholdMemberRemoved) {
            $title = 'Member removed from household';
            $message = sprintf('%s was removed from household %s', $event->user->email, $household->name);
        }

        foreach ($household->users as $user) {
            if ($actorId && $user->id === $actorId) {
                continue;
            }

            AppNotification::create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
            ]);
        }
    }
}
