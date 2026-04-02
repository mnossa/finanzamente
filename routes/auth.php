<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\PlanSelectionController;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

Route::middleware('guest')->group(function () {
    // Selezione piano prima della registrazione
    Route::get('scegli-piano', [PlanSelectionController::class, 'show'])
        ->name('plan.select');

    Route::get('registrati', [RegisteredUserController::class, 'create'])
        ->name('register');

    // Rate limiting avanzato: max 5 tentativi ogni 2 minuti per IP, delay progressivo, logging GDPR compliant
    Route::post('registrati', [RegisteredUserController::class, 'store'])
        ->middleware(['adv-throttle:5,2', ProtectAgainstSpam::class])
        ->name('register.store');

    Route::get('accedi', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    // Rate limiting avanzato: max 5 tentativi ogni 2 minuti per IP, delay progressivo, logging GDPR compliant
    Route::post('accedi', [AuthenticatedSessionController::class, 'store'])
        ->middleware(['adv-throttle:5,2'])
        ->name('login.store');

    Route::get('password-dimenticata', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Rate limiting avanzato: max 5 tentativi ogni 2 minuti per IP, delay progressivo, logging GDPR compliant
    Route::post('password-dimenticata', [PasswordResetLinkController::class, 'store'])
        ->name('password.email')
        ->middleware(['adv-throttle:5,2']);

    Route::get('reimposta-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    // Rate limiting avanzato: max 5 tentativi ogni 2 minuti per IP, delay progressivo, logging GDPR compliant
    Route::post('reimposta-password', [NewPasswordController::class, 'store'])
        ->name('password.store')
        ->middleware(['adv-throttle:5,2']);
});

Route::middleware('auth')->group(function () {

    Route::get('verifica-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    // Rate limiting avanzato: max 6 tentativi ogni 1 minuto per IP, delay progressivo, logging GDPR compliant
    Route::get('verifica-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'adv-throttle:6,1'])
        ->name('verification.verify');

    // Rate limiting avanzato: max 6 tentativi ogni 1 minuto per IP, delay progressivo, logging GDPR compliant
    Route::post('email/notifica-verifica', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('adv-throttle:6,1')
        ->name('verification.send');

    Route::get('conferma-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('conferma-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('aggiorna-password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('disconnettiti', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
