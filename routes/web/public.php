<?php

use App\Http\Controllers\HouseholdInvitationController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WaitlistController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', [WelcomeController::class, 'index'])->name('home');

// Pagine legali — visibili solo al proprietario durante il pre-lancio
Route::get('/privacy', function () {
    if (config('prelaunch.enabled') && strtolower(optional(request()->user())->email ?? '') !== strtolower(config('prelaunch.owner_email', ''))) {
        abort(404);
    }

    return view('legal.privacy');
})->name('legal.privacy');

Route::get('/cookie', function () {
    if (config('prelaunch.enabled') && strtolower(optional(request()->user())->email ?? '') !== strtolower(config('prelaunch.owner_email', ''))) {
        abort(404);
    }

    return view('legal.cookies');
})->name('legal.cookies');

Route::get('/termini', function () {
    if (config('prelaunch.enabled') && strtolower(optional(request()->user())->email ?? '') !== strtolower(config('prelaunch.owner_email', ''))) {
        abort(404);
    }

    return view('legal.terms');
})->name('legal.terms');

// Landing page per target specifici
Route::get('/per-investitori', [LandingController::class, 'investitori'])->name('landing.investitori');
Route::get('/per-famiglie', [LandingController::class, 'famiglie'])->name('landing.famiglie');
Route::get('/per-freelance', [LandingController::class, 'freelance'])->name('landing.freelance');
Route::get('/per-lavoratori', [LandingController::class, 'lavoratori'])->name('landing.lavoratori');
Route::get('/per-pianificatori', [LandingController::class, 'pianificatori'])->name('landing.pianificatori');
Route::get('/per-tech-savvy', [LandingController::class, 'techSavvy'])->name('landing.tech-savvy');
Route::get('/crescita-personale', [LandingController::class, 'crescita'])->name('landing.crescita');

// robots.txt dinamico con Sitemap URL corretto
Route::get('/robots.txt', [RobotsController::class, 'index']);

// Sitemap XML — generata tramite `php artisan sitemap:generate` (schedulato ogni domenica)
Route::get('/sitemap.xml', function () {
    $path = public_path('sitemap.xml');
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, ['Content-Type' => 'application/xml']);
})->name('sitemap');

// Webhook Telegram — chiamata server-to-server, senza CSRF né sessione
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook')
    ->withoutMiddleware([
        ValidateCsrfToken::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ]);

// Webhook Mollie — chiamata server-to-server, senza CSRF né sessione
Route::post('/mollie/webhook', [MollieWebhookController::class, 'handle'])
    ->name('mollie.webhook')
    ->withoutMiddleware([
        ValidateCsrfToken::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ]);

// Rotte pubbliche per inviti household
Route::get('/inviti/{token}/registrati', [HouseholdInvitationController::class, 'showRegisterForm'])
    ->name('household-invitations.register');
Route::post('/inviti/{token}/registrati', [HouseholdInvitationController::class, 'registerAndAccept'])
    ->name('household-invitations.register.store');
Route::get('/inviti/{token}/accetta', [HouseholdInvitationController::class, 'acceptInvitation'])
    ->name('household-invitations.accept');

// Waitlist Pro — iscrizione pubblica (pre-lancio)
Route::post('/waitlist', [WaitlistController::class, 'store'])
    ->middleware(['adv-throttle:3,5'])
    ->name('waitlist.store');

// Webhook Tally → Brevo (escluso da CSRF e sessione: chiamata server-to-server)
Route::post('/webhooks/tally', [WaitlistController::class, 'tallyWebhook'])
    ->withoutMiddleware([
        ValidateCsrfToken::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ])
    ->name('webhooks.tally');

// Pagina di conferma dopo double opt-in
Route::get('/waitlist/confermata', fn () => view('waitlist.confirmed'))
    ->name('waitlist.confirmed');

// Link di conferma iscrizione waitlist (DOI manuale via URL firmato)
Route::get('/waitlist/conferma', [WaitlistController::class, 'confirm'])
    ->name('waitlist.confirm');

// Pagina "in arrivo" — CTA Base in modalità pre-lancio
Route::get('/in-arrivo', fn () => view('prelaunch.coming-soon'))
    ->name('prelaunch.coming-soon');
