<?php

namespace App\Http\Middleware;

use App\Models\AppNotification;
use App\Models\Consent;
use App\Services\HouseholdPermissionService;
use App\Services\ModuleAccessService;
use Illuminate\Http\Request;
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
                'user' => $user,
            ],
            'activeHousehold' => fn () => $user?->activeHousehold ? [
                'id' => $user->activeHousehold->id,
                'name' => $user->activeHousehold->name,
                'is_owner' => $user->activeHousehold->owner_user_id === $user->id,
            ] : null,
            'permissions' => [
                'canModify' => $canModify,
                'role' => $userRole,
            ],
            'modules' => $availableModules,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'notifications' => fn () => $user ? [
                'unread_count' => AppNotification::where('user_id', $user->id)->where('read', false)->count(),
                'items' => AppNotification::where('user_id', $user->id)
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get()
                    ->map(fn ($n) => [
                        'id' => $n->id,
                        'title' => $n->title,
                        'message' => $n->message,
                        'read' => $n->read,
                        'created_at' => $n->created_at->diffForHumans(),
                    ])
                    ->toArray(),
            ] : ['unread_count' => 0, 'items' => []],
            'googleDrive' => [
                'clientId' => config('services.google_drive.client_id', ''),
                'apiKey' => config('services.google_drive.api_key', ''),
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
            'isAdmin' => fn () => $user ? strtolower($user->email) === strtolower(config('prelaunch.magazine_admin_email', '')) : false,
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
        ];
    }
}
