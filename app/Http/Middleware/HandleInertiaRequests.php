<?php

namespace App\Http\Middleware;

use App\Models\Consent;
use App\Models\User;
use App\Services\FormulaWidgetDataVersionService;
use App\Services\HouseholdPermissionService;
use App\Services\ModuleAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $permissionService = app(HouseholdPermissionService::class);
        $moduleService = app(ModuleAccessService::class);

        // Determina se l'utente può modificare i dati nella household attiva
        $canModify = false;
        $userRole = null;

        if ($user && $user->active_household_id) {
            $canModify = $permissionService->canModify($user, $user->active_household_id);
            $membership = $permissionService->getMembership($user, $user->active_household_id);
            $userRole = $membership?->role ?? 'member';
        }

        // Moduli disponibili per l'utente
        $availableModules = $user ? $moduleService->getAllModulesWithAccess($user) : [];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? $this->serializeAuthUser($user) : null,
            ],
            'activeHousehold' => fn () => $user?->activeHousehold ? [
                'id' => $user->activeHousehold->id,
                'name' => $user->activeHousehold->name,
                'is_owner' => $user->activeHousehold->owner_user_id === $user->id,
            ] : null,
            'formulaWidgetDataVersion' => fn () => $user
                ? app(FormulaWidgetDataVersionService::class)->resolveForUser($user)
                : null,
            'permissions' => [
                'canModify' => $canModify,
                'role' => $userRole,
            ],
            'modules' => $availableModules,
            'features' => config('features'),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'duplicateWidget' => fn () => $request->session()->get('duplicateWidget'),
                'duplicateMarketplaceWidget' => fn () => $request->session()->get('duplicateMarketplaceWidget'),
                'undoFormulaWidget' => fn () => $request->session()->get('undoFormulaWidget'),
            ],
            'notifications' => fn () => $user
                ? ['deferred' => true, 'unread_count' => 0, 'items' => []]
                : ['deferred' => false, 'unread_count' => 0, 'items' => []],
            'googleDrive' => [
                'clientId' => config('services.google_drive.client_id', ''),
                'apiKey' => config('services.google_drive.api_key', ''),
            ],
            'umami' => [
                'websiteId' => (string) config('services.umami.website_id', ''),
            ],
            'plan' => fn () => $user ? [
                'current' => $user->isPro() ? 'pro' : 'base',
                'pro_enabled' => config('plans.pro_enabled', true),
                'waitlist_enabled' => config('prelaunch.waitlist_enabled', false),
                'expires_at' => $user->plan_expires_at?->toISOString(),
                'days_until_expiry' => $user->planExpiresInDays(),
                'excess_accounts' => $user->excessAccountsCount(),
                'excess_households' => $user->excessHouseholdsCount(),
            ] : null,
            'isEarlyBird' => fn () => $user ? (bool) $user->is_early_bird : false,
            'isAdmin' => fn () => $user ? strtolower($user->email) === strtolower(config('prelaunch.admin_email', '')) : false,
            'privacy' => fn () => [
                // Default prudente: analytics disabilitato finché non c'è consenso esplicito.
                'analytics_enabled' => $user
                    ? Consent::query()
                        ->where('user_id', $user->id)
                        ->where('purpose', 'analytics_tracking')
                        ->where('status', 'granted')
                        ->exists()
                    : false,
            ],
            'marketing' => fn () => [
                'can_register' => Route::has('register') && ! config('prelaunch.enabled', false),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAuthUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'preferences' => $user->preferences,
            'profile_settings' => $user->profile_settings,
            'default_currency_code' => $user->default_currency_code,
            'income_band' => $user->income_band,
            'macro_region' => $user->macro_region,
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'active_household_id' => $user->active_household_id,
            'email_verified_at' => $user->email_verified_at,
            'profile_completed' => $user->profile_completed,
            'status' => $user->status,
        ];
    }
}
