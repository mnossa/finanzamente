<?php

namespace App\Http\Middleware;

use App\Services\HouseholdPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware che verifica che l'utente non sia in modalità "solo visualizzazione" (guest).
 * Blocca le operazioni di creazione, modifica ed eliminazione per gli ospiti.
 */
class EnsureCanModify
{
    public function __construct(
        protected HouseholdPermissionService $permissionService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active_household_id) {
            abort(403, 'Devi selezionare una household attiva.');
        }

        if ($this->permissionService->isViewOnly($user, $user->active_household_id)) {
            abort(403, 'Non hai i permessi per eseguire questa operazione. Il tuo ruolo è solo visualizzazione.');
        }

        return $next($request);
    }
}
