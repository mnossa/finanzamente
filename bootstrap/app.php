<?php

use App\Http\Middleware\AdvancedRateLimitWithDelay;
use App\Http\Middleware\EnsureCanModify;
use App\Http\Middleware\EnsureHasActiveHousehold;
use App\Http\Middleware\EnsureProfileCompleted;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\OwnerMiddleware;
use App\Http\Middleware\PreLaunchMiddleware;
use App\Http\Middleware\RequiresPro;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Fidati dei proxy (Caddy) per X-Forwarded-Proto, così Laravel genera URL https://
        $middleware->trustProxies(at: '*');

        // Escludi i webhook da CSRF (chiamate server-to-server autenticate via firma HMAC)
        $middleware->validateCsrfTokens(except: [
            '/webhooks/*',
            '/telegram/webhook',
            '/mollie/webhook',
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'household' => EnsureHasActiveHousehold::class,
            'can-modify' => EnsureCanModify::class,
            'profile-completed' => EnsureProfileCompleted::class,
            'adv-throttle' => AdvancedRateLimitWithDelay::class,
            'requires-pro' => RequiresPro::class,
            'pre-launch' => PreLaunchMiddleware::class,
            'owner' => OwnerMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
