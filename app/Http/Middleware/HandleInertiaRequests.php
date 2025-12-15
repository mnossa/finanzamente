<?php

namespace App\Http\Middleware;

use App\Services\HouseholdPermissionService;
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
        
        // Determina se l'utente può modificare i dati nella household attiva
        $canModify = false;
        $userRole = null;
        
        if ($user && $user->active_household_id) {
            $canModify = $permissionService->canModify($user, $user->active_household_id);
            $membership = $permissionService->getMembership($user, $user->active_household_id);
            $userRole = $membership?->role ?? 'member';
        }
        
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
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
