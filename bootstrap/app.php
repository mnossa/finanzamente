<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Escludi i webhook da CSRF (chiamate server-to-server autenticate via firma HMAC)
        $middleware->validateCsrfTokens(except: [
            '/webhooks/*',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'household' => \App\Http\Middleware\EnsureHasActiveHousehold::class,
            'can-modify' => \App\Http\Middleware\EnsureCanModify::class,
            'profile-completed' => \App\Http\Middleware\EnsureProfileCompleted::class,
            'adv-throttle' => \App\Http\Middleware\AdvancedRateLimitWithDelay::class,
            'requires-pro' => \App\Http\Middleware\RequiresPro::class,
            'pre-launch' => \App\Http\Middleware\PreLaunchMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
