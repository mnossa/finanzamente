<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorAuthenticationController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $twoFactorService,
    ) {}

    public function enable(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($this->twoFactorService->hasConfirmedTwoFactor($user)) {
            return redirect()->route('profile.edit')
                ->with('error', 'L\'autenticazione a due fattori è già attiva.');
        }

        $secret = $this->twoFactorService->generateSecret();

        $request->session()->put('two_factor_setup_secret', $secret);

        return Inertia::render('Profile/TwoFactorSetup', [
            'qrCodeSvg' => $this->twoFactorService->getQrCodeSvg($user, $secret),
            'manualSetupKey' => trim(chunk_split($secret, 4, ' ')),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $secret = $request->session()->pull('two_factor_setup_secret');

        if (! is_string($secret) || $secret === '') {
            return redirect()->route('profile.two-factor.enable')
                ->with('error', 'Sessione di configurazione scaduta. Riprova.');
        }

        if (! $this->twoFactorService->verifyPendingSecret($secret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => 'Il codice non è valido. Controlla l\'app di autenticazione e riprova.',
            ]);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->twoFactorService->hashRecoveryCodes($recoveryCodes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->flash('two_factor_recovery_codes', $recoveryCodes);

        return redirect()->route('profile.edit')
            ->with('success', 'Autenticazione a due fattori attivata.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $this->twoFactorService->hasConfirmedTwoFactor($user)) {
            return redirect()->route('profile.edit');
        }

        $codeValid = $this->twoFactorService->verifyCode($user, $validated['code'])
            || $this->twoFactorService->verifyRecoveryCode($user, $validated['code']);

        if (! $codeValid) {
            throw ValidationException::withMessages([
                'code' => 'Il codice non è valido.',
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return redirect()->route('profile.edit')
            ->with('success', 'Autenticazione a due fattori disattivata.');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $this->twoFactorService->hasConfirmedTwoFactor($user)) {
            return redirect()->route('profile.edit');
        }

        if (! $this->twoFactorService->verifyCode($user, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => 'Il codice non è valido.',
            ]);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $this->twoFactorService->hashRecoveryCodes($recoveryCodes),
        ])->save();

        $request->session()->flash('two_factor_recovery_codes', $recoveryCodes);

        return redirect()->route('profile.edit')
            ->with('success', 'Nuovi codici di recupero generati.');
    }
}
