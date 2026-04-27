<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [\App\Http\Controllers\WelcomeController::class, 'index'])->name('home');

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
Route::get('/per-investitori', [\App\Http\Controllers\LandingController::class, 'investitori'])->name('landing.investitori');
Route::get('/per-famiglie', [\App\Http\Controllers\LandingController::class, 'famiglie'])->name('landing.famiglie');
Route::get('/per-freelance', [\App\Http\Controllers\LandingController::class, 'freelance'])->name('landing.freelance');
Route::get('/per-lavoratori', [\App\Http\Controllers\LandingController::class, 'lavoratori'])->name('landing.lavoratori');
Route::get('/per-pianificatori', [\App\Http\Controllers\LandingController::class, 'pianificatori'])->name('landing.pianificatori');
Route::get('/per-tech-savvy', [\App\Http\Controllers\LandingController::class, 'techSavvy'])->name('landing.tech-savvy');
Route::get('/crescita-personale', [\App\Http\Controllers\LandingController::class, 'crescita'])->name('landing.crescita');

// robots.txt dinamico con Sitemap URL corretto
Route::get('/robots.txt', [\App\Http\Controllers\RobotsController::class, 'index']);

// Sitemap XML — generata tramite `php artisan sitemap:generate` (schedulato ogni domenica)
Route::get('/sitemap.xml', function () {
    $path = public_path('sitemap.xml');
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, ['Content-Type' => 'application/xml']);
})->name('sitemap');

// Webhook Telegram — chiamata server-to-server, senza CSRF né sessione
Route::post('/telegram/webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
        \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
    ]);

// Webhook Mollie — chiamata server-to-server, senza CSRF né sessione
Route::post('/mollie/webhook', [\App\Http\Controllers\MollieWebhookController::class, 'handle'])
    ->name('mollie.webhook')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
        \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
    ]);

// Rotte pubbliche per inviti household
Route::get('/inviti/{token}/registrati', [\App\Http\Controllers\HouseholdInvitationController::class, 'showRegisterForm'])
    ->name('household-invitations.register');
Route::post('/inviti/{token}/registrati', [\App\Http\Controllers\HouseholdInvitationController::class, 'registerAndAccept'])
    ->name('household-invitations.register.store');
Route::get('/inviti/{token}/accetta', [\App\Http\Controllers\HouseholdInvitationController::class, 'acceptInvitation'])
    ->name('household-invitations.accept');

// Waitlist Pro — iscrizione pubblica (pre-lancio)
Route::post('/waitlist', [\App\Http\Controllers\WaitlistController::class, 'store'])
    ->middleware(['adv-throttle:3,5'])
    ->name('waitlist.store');

// Webhook Tally → Brevo (escluso da CSRF e sessione: chiamata server-to-server)
Route::post('/webhooks/tally', [\App\Http\Controllers\WaitlistController::class, 'tallyWebhook'])
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
        \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
    ])
    ->name('webhooks.tally');

// Pagina di conferma dopo double opt-in
Route::get('/waitlist/confermata', fn () => view('waitlist.confirmed'))
    ->name('waitlist.confirmed');

// Link di conferma iscrizione waitlist (DOI manuale via URL firmato)
Route::get('/waitlist/conferma', [\App\Http\Controllers\WaitlistController::class, 'confirm'])
    ->name('waitlist.confirm');

// Pagina "in arrivo" — CTA Base in modalità pre-lancio
Route::get('/in-arrivo', fn () => view('prelaunch.coming-soon'))
    ->name('prelaunch.coming-soon');
