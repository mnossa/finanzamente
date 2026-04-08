<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware per il periodo di pre-lancio.
 *
 * Quando PRE_LAUNCH_MODE=true, solo l'utente con l'email specificata in
 * PRE_LAUNCH_OWNER_EMAIL può accedere alla dashboard e alle rotte autenticate.
 * Tutti gli altri utenti vengono disconnessi e reindirizzati alla homepage
 * con un messaggio informativo.
 *
 * Gli utenti non ancora autenticati vengono bloccati anche dalla registrazione.
 */
class PreLaunchMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('prelaunch.enabled', false)) {
            return $next($request);
        }

        $ownerEmail = config('prelaunch.owner_email', '');
        $user = $request->user();

        if ($user && strtolower($user->email) === strtolower($ownerEmail)) {
            return $next($request);
        }

        // Disconnette l'utente non autorizzato se autenticato
        if ($user) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('home')
            ->with('info', 'FinanzaMente è in fase di pre-lancio. Iscriviti alla waitlist per essere avvisato al lancio!');
    }
}
