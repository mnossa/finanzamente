<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Honeypot\ProtectAgainstSpam;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {
        // Applica honeypot solo alla registrazione
        $this->middleware(ProtectAgainstSpam::class)->only('store');
    }

    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'user_type' => 'nullable|in:persona',
            'fiscal_code' => [
                'nullable',
                'string',
                'size:16',
                'regex:/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i',
            ],
            'marketing_email' => 'nullable|boolean',
            'analytics_tracking' => 'nullable|boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'persona',
            'fiscal_code' => $request->filled('fiscal_code') ? strtoupper($request->fiscal_code) : null,
            'vat_number' => null,
        ]);

        $this->persistRegistrationConsents($request, $user);

        // Invia l'email di verifica manualmente invece di usare l'evento Registered
        // per evitare invii duplicati
        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect(route('verification.notice', absolute: false));
    }

    private function persistRegistrationConsents(Request $request, User $user): void
    {
        $contextBase = [
            'source' => 'web_register',
            'legal_basis' => 'consent',
            'policy_version' => config('legal.privacy_policy_version'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Consensi obbligatori: registrazione implica accettazione policy e termini.
        $this->consentService->setConsent($user, 'privacy_policy_ack', 'granted', $contextBase);
        $this->consentService->setConsent($user, 'terms_ack', 'granted', $contextBase);

        // Consensi opzionali: default revoked se non selezionati esplicitamente.
        $optionalPurposes = ['marketing_email', 'analytics_tracking'];
        foreach ($optionalPurposes as $purpose) {
            $this->consentService->setConsent(
                $user,
                $purpose,
                $request->boolean($purpose) ? 'granted' : 'revoked',
                $contextBase
            );
        }
    }
}
