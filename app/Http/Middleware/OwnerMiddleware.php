<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe l'accesso all'email del proprietario configurata in PRE_LAUNCH_OWNER_EMAIL.
 * Usato per proteggere le rotte di amministrazione (es. gestione magazine).
 */
class OwnerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $ownerEmail = config('prelaunch.owner_email', '');

        if ($user && $ownerEmail && strtolower($user->email) === strtolower($ownerEmail)) {
            return $next($request);
        }

        abort(403, 'Accesso riservato al proprietario.');
    }
}
