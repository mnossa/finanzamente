<?php

namespace App\Providers;

use App\Models\Household;
use App\Observers\HouseholdObserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Imposta la lingua italiana per Carbon (date)
        Carbon::setLocale('it');

        // Registra observer per la creazione automatica delle categorie
        Household::observe(HouseholdObserver::class);

        Gate::policy(\App\Models\BankImportLayout::class, \App\Policies\BankImportLayoutPolicy::class);
    }
}
