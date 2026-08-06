<?php

use App\Http\Controllers\HouseholdInvitationController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SharedFormulaController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WelcomeController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', [WelcomeController::class, 'index'])->name('home');

Route::get('/privacy', fn () => view('legal.privacy'))->name('legal.privacy');
Route::get('/cookie', fn () => view('legal.cookies'))->name('legal.cookies');
Route::get('/termini', fn () => view('legal.terms'))->name('legal.terms');

// Anteprima pubblica widget formula (guest)
Route::get('/shared/formula/{share_token}', [SharedFormulaController::class, 'show'])->name('shared.formula.show');

Route::get('/robots.txt', [RobotsController::class, 'index']);

Route::get('/sitemap.xml', function () {
    $path = public_path('sitemap.xml');
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, ['Content-Type' => 'application/xml']);
})->name('sitemap');

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook')
    ->withoutMiddleware([
        ValidateCsrfToken::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ]);

Route::get('/inviti/{token}/registrati', [HouseholdInvitationController::class, 'showRegisterForm'])
    ->name('household-invitations.register');
Route::post('/inviti/{token}/registrati', [HouseholdInvitationController::class, 'registerAndAccept'])
    ->name('household-invitations.register.store');
Route::get('/inviti/{token}/accetta', [HouseholdInvitationController::class, 'acceptInvitation'])
    ->name('household-invitations.accept');
