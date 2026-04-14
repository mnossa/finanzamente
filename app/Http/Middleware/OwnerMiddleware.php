<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe l'accesso all'email dell'admin magazine configurata in MAGAZINE_ADMIN_EMAIL.
 * Usato per proteggere le rotte di amministrazione del magazine.
 */
class OwnerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $ownerEmail = config('prelaunch.magazine_admin_email', '');

        if ($user && $ownerEmail && strtolower($user->email) === strtolower($ownerEmail)) {
            return $next($request);
        }

        abort(403, 'Accesso riservato al proprietario.');
    }
}
