<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Consent;
use App\Models\Currency;
use App\Services\BrevoMarketingService;
use App\Services\ConsentService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ConsentService $consentService,
        private readonly BrevoMarketingService $brevoMarketingService
    ) {}

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $optionalPurposes = ['marketing_email', 'analytics_tracking'];
        $existing = Consent::query()
            ->where('user_id', $user->id)
            ->whereIn('purpose', $optionalPurposes)
            ->get()
            ->keyBy('purpose');

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'consents' => [
                'marketing_email' => optional($existing->get('marketing_email'))->status === 'granted',
                'analytics_tracking' => optional($existing->get('analytics_tracking'))->status === 'granted',
            ],
            'currencies' => Currency::orderBy('code')
                ->get(['code', 'name', 'symbol'])
                ->map(fn ($c) => ['code' => $c->code, 'name' => $c->name, 'symbol' => $c->symbol])
                ->all(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function updateConsents(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'marketing_email' => 'required|boolean',
            'analytics_tracking' => 'required|boolean',
        ]);

        $user = $request->user();
        $context = [
            'source' => 'profile_settings',
            'legal_basis' => 'consent',
            'policy_version' => '2026-04-28-v1',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        foreach ($validated as $purpose => $isGranted) {
            $this->consentService->setConsent(
                $user,
                $purpose,
                $isGranted ? 'granted' : 'revoked',
                $context
            );
        }

        $this->brevoMarketingService->syncMarketingConsent(
            $user->email,
            (bool) $validated['marketing_email']
        );

        return Redirect::route('profile.edit')->with('success', 'Preferenze privacy aggiornate.');
    }

    public function exportConsents(Request $request): StreamedResponse
    {
        $user = $request->user();
        $consents = Consent::query()
            ->with(['events' => fn ($query) => $query->orderBy('occurred_at')])
            ->where('user_id', $user->id)
            ->orderBy('purpose')
            ->get();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
            'consents' => $consents->map(function (Consent $consent) {
                return [
                    'purpose' => $consent->purpose,
                    'status' => $consent->status,
                    'source' => $consent->source,
                    'legal_basis' => $consent->legal_basis,
                    'policy_version' => $consent->policy_version,
                    'granted_at' => optional($consent->granted_at)->toIso8601String(),
                    'revoked_at' => optional($consent->revoked_at)->toIso8601String(),
                    'expires_at' => optional($consent->expires_at)->toIso8601String(),
                    'metadata' => $consent->metadata,
                    'events' => $consent->events->map(fn ($event) => [
                        'event_type' => $event->event_type,
                        'old_status' => $event->old_status,
                        'new_status' => $event->new_status,
                        'source' => $event->source,
                        'policy_version' => $event->policy_version,
                        'occurred_at' => optional($event->occurred_at)->toIso8601String(),
                        'metadata' => $event->metadata,
                    ])->values(),
                ];
            })->values(),
        ];

        $filename = 'consensi-utenti-'.$user->id.'-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function revokeOptionalConsents(Request $request): RedirectResponse
    {
        $user = $request->user();
        $context = [
            'source' => 'profile_settings',
            'legal_basis' => 'consent',
            'policy_version' => '2026-04-28-v1',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        foreach (['marketing_email', 'analytics_tracking'] as $purpose) {
            $this->consentService->setConsent($user, $purpose, 'revoked', $context);
        }

        $this->brevoMarketingService->syncMarketingConsent($user->email, false);

        return Redirect::route('profile.edit')->with('success', 'Tutti i consensi opzionali sono stati revocati.');
    }

    public function syncAnalyticsConsent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'analytics_tracking' => 'required|boolean',
        ]);

        $this->consentService->setConsent(
            $request->user(),
            'analytics_tracking',
            $validated['analytics_tracking'] ? 'granted' : 'revoked',
            [
                'source' => 'public_blade_sync',
                'legal_basis' => 'consent',
                'policy_version' => '2026-04-28-v1',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        return response()->json(['status' => 'ok']);
    }
}
