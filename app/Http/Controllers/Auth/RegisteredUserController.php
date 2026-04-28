<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BrevoMarketingService;
use App\Services\ConsentService;
use App\Services\PlanService;
use App\Services\WaitlistService;
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
        private readonly PlanService $planService,
        private readonly WaitlistService $waitlistService,
        private readonly ConsentService $consentService,
        private readonly BrevoMarketingService $brevoMarketingService,
    ) {
        // Applica honeypot solo alla registrazione
        $this->middleware(ProtectAgainstSpam::class)->only('store');
    }

    /**
     * Display the registration view.
     * Accetta un parametro opzionale `plan` (base/pro) e `billing_cycle` (monthly/annual).
     * In modalità pre-lancio, solo il proprietario può accedere alla registrazione.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        // Blocca la registrazione in modalità pre-lancio per utenti non autorizzati
        if (config('prelaunch.enabled', false)) {
            $ownerEmail = strtolower(config('prelaunch.owner_email', ''));
            $incomingEmail = strtolower(trim($request->query('email', '')));
            if (empty($ownerEmail) || $incomingEmail !== $ownerEmail) {
                return redirect()->route('home')
                    ->with('info', 'Finanzamente è in fase di pre-lancio. Iscriviti alla waitlist per essere avvisato al lancio!');
            }
        }

        $selectedPlan = $request->query('plan', 'base');
        $billingCycle = $request->query('billing_cycle', 'monthly');

        // Validazione dei parametri di piano
        if (! $this->planService->planExists($selectedPlan)) {
            $selectedPlan = 'base';
        }

        if (! in_array($billingCycle, ['monthly', 'annual'])) {
            $billingCycle = 'monthly';
        }

        // Determina se l'utente è un early bird (ha una firma HMAC valida nella URL)
        $isEarlyBird = $this->resolveEarlyBird($request);

        return Inertia::render('Auth/Register', [
            'selectedPlan' => $selectedPlan,
            'billingCycle' => $billingCycle,
            'plans' => $this->planService->getPlansForFrontend(),
            'proEnabled' => $this->planService->isProEnabled(),
            'isEarlyBird' => $isEarlyBird,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Blocca la registrazione in modalità pre-lancio per utenti non autorizzati
        if (config('prelaunch.enabled', false)) {
            $ownerEmail = strtolower(config('prelaunch.owner_email', ''));
            $incomingEmail = strtolower(trim($request->input('email', '')));
            if (empty($ownerEmail) || $incomingEmail !== $ownerEmail) {
                return redirect()->route('home')
                    ->with('info', 'Finanzamente è in fase di pre-lancio. Iscriviti alla waitlist per essere avvisato al lancio!');
            }
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'user_type' => 'required|in:persona,partita_iva',
            'fiscal_code' => [
                'nullable',
                'string',
                'size:16',
                'regex:/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i',
            ],
            'vat_number' => [
                'nullable',
                'string',
                'size:11',
                'regex:/^[0-9]{11}$/',
            ],
            'selected_plan' => 'nullable|string|in:base,pro',
            'billing_cycle' => 'nullable|string|in:monthly,annual',
            'sig' => 'nullable|string|size:64',
            'marketing_email' => 'nullable|boolean',
            'analytics_tracking' => 'nullable|boolean',
        ]);

        $selectedPlan = $request->input('selected_plan', 'base');

        // Se il piano Pro non è abilitato, forza al piano base
        if ($selectedPlan === 'pro' && ! $this->planService->isProEnabled()) {
            $selectedPlan = 'base';
        }

        // Determina se l'utente è un early bird e salva il flag
        $isEarlyBird = $this->resolveEarlyBird($request);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type,
            'fiscal_code' => $request->user_type === 'persona' && $request->filled('fiscal_code') ? strtoupper($request->fiscal_code) : null,
            'vat_number' => $request->user_type === 'partita_iva' && $request->filled('vat_number') ? $request->vat_number : null,
            'plan' => 'base', // inizia sempre con il piano base, verrà aggiornato dopo il pagamento
            'is_early_bird' => $isEarlyBird,
        ]);

        $this->persistRegistrationConsents($request, $user);
        $this->brevoMarketingService->syncMarketingConsent(
            $user->email,
            $request->boolean('marketing_email')
        );

        // Invia l'email di verifica manualmente invece di usare l'evento Registered
        // per evitare invii duplicati
        $user->sendEmailVerificationNotification();

        Auth::login($user);

        // Se l'utente ha scelto il piano Pro, salva in sessione per il checkout post-verifica
        if ($selectedPlan === 'pro') {
            $request->session()->put('pending_pro_plan', [
                'billing_cycle' => $request->input('billing_cycle', 'monthly'),
            ]);
        }

        return redirect(route('verification.notice', absolute: false));
    }

    private function persistRegistrationConsents(Request $request, User $user): void
    {
        $contextBase = [
            'source' => 'web_register',
            'legal_basis' => 'consent',
            'policy_version' => '2026-04-28-v1',
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

    /**
     * Determina se l'utente è un early bird verificando la firma HMAC nella URL.
     * La firma è valida se è una stringa esadecimale di 64 caratteri (SHA256 hex)
     * e corrisponde all'HMAC dell'email con APP_KEY.
     */
    private function resolveEarlyBird(Request $request): bool
    {
        $email = strtolower(trim($request->input('email', '') ?: $request->query('email', '')));
        $sig = $request->input('sig', '') ?: $request->query('sig', '');

        if (empty($email) || empty($sig) || strlen($sig) !== 64 || ! ctype_xdigit($sig)) {
            return false;
        }

        return $this->waitlistService->verifySignature($email, $sig);
    }
}
