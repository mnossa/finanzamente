<?php

namespace App\Providers;

use App\Actions\Passkeys\GeneratePlatformRegistrationOptions;
use App\Http\Responses\PasskeyLoginResponse as AppPasskeyLoginResponse;
use App\Models\BankImportLayout;
use App\Models\Household;
use App\Models\Investment;
use App\Models\User;
use App\Observers\HouseholdObserver;
use App\Observers\InvestmentObserver;
use App\Policies\BankImportLayoutPolicy;
use Artesaos\SEOTools\Facades\JsonLdMulti;
use Carbon\Carbon;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Passkeys;
use Laravel\Pulse\Facades\Pulse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        AliasLoader::getInstance()->alias('JsonLdMulti', JsonLdMulti::class);

        $this->app->bind(GenerateRegistrationOptions::class, GeneratePlatformRegistrationOptions::class);
        $this->app->singleton(PasskeyLoginResponse::class, AppPasskeyLoginResponse::class);

        // Telescope: require-dev, solo local/staging e TELESCOPE_ENABLED=true. Mai in produzione pubblica.
        // class_exists con stringa: evita autoload fallito se package assente (composer --no-dev).
        if (
            class_exists('Laravel\\Telescope\\TelescopeApplicationServiceProvider')
            && $this->app->environment(['local', 'staging'])
            && filter_var(env('TELESCOPE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
        ) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passkeys::useUserModel(User::class);

        Vite::prefetch(concurrency: 1);

        // Imposta la lingua italiana per Carbon (date)
        Carbon::setLocale('it');

        // Registra observer per la creazione automatica delle categorie
        Household::observe(HouseholdObserver::class);
        Investment::observe(InvestmentObserver::class);

        Gate::policy(BankImportLayout::class, BankImportLayoutPolicy::class);

        Gate::define('viewPulse', function (?User $user) {
            if (! $user) {
                return false;
            }

            $adminEmail = config('app.admin_email', '');

            return $adminEmail !== ''
                && strtolower($user->email) === strtolower($adminEmail);
        });

        // Pulse UI: nessun nome/email/Gravatar — solo ID anonimo.
        Pulse::user(fn ($user) => [
            'name' => 'Utente #'.$user->id,
            'extra' => '',
            'avatar' => '',
        ]);
    }
}
