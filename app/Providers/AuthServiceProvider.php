<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Category;
use App\Models\InterHouseholdTransfer;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Policies\AccountPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\InterHouseholdTransferPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\TransferPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Account::class => AccountPolicy::class,
        Category::class => CategoryPolicy::class,
        InterHouseholdTransfer::class => InterHouseholdTransferPolicy::class,
        Transaction::class => TransactionPolicy::class,
        Transfer::class => TransferPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Additional gates can be defined here if needed
    }
}
