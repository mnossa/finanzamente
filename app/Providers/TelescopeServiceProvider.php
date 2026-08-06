<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        // Mai registrare entries fuori da local/staging.
        Telescope::filter(function (IncomingEntry $entry) {
            if (! $this->app->environment(['local', 'staging'])) {
                return false;
            }

            return true;
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters([
            '_token',
            'password',
            'password_confirmation',
            'current_password',
            'amount',
            'importo',
            'description',
            'descrizione',
            'note',
            'notes',
            'email',
            'iban',
        ]);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
        ]);
    }

    /**
     * Register the Telescope gate.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (?User $user) {
            if (! $user) {
                return false;
            }

            $adminEmail = config('app.admin_email', '');

            return $adminEmail !== ''
                && strtolower($user->email) === strtolower($adminEmail);
        });
    }
}
