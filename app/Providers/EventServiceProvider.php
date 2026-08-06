<?php

namespace App\Providers;

use App\Events\HouseholdMemberAdded;
use App\Events\HouseholdMemberRemoved;
use App\Events\ModelChanged;
use App\Listeners\NotifyHouseholdMembers;
use App\Listeners\UpdateAccountBalance;
use App\Listeners\UpdateDebtCreditBalance;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        ModelChanged::class => [
            UpdateAccountBalance::class,
            UpdateDebtCreditBalance::class,
        ],
        HouseholdMemberAdded::class => [
            NotifyHouseholdMembers::class,
        ],
        HouseholdMemberRemoved::class => [
            NotifyHouseholdMembers::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
