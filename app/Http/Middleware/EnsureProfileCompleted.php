<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware che verifica se l'utente ha completato il quiz di profilazione.
 * Se non l'ha completato, viene reindirizzato alla pagina del quiz.
 * 
 * NOTA: Gli utenti che hanno già una household (utenti esistenti) non vengono
 * bloccati per garantire retrocompatibilità.
 */
class EnsureProfileCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Se l'utente ha già almeno una household, significa che è un utente esistente
        // prima dell'implementazione del quiz. Gli permettiamo di accedere senza restrizioni.
        if ($user->households()->count() > 0) {
            return $next($request);
        }

        // Se l'utente non ha completato il quiz di profilazione, reindirizza al quiz
        if (!$user->profile_completed) {
            // Permetti l'accesso alla rotta del quiz stesso e alle rotte di logout
            if (!$request->routeIs('profile-quiz.*') && !$request->routeIs('logout')) {
                return redirect()->route('profile-quiz.show');
            }
        }

        return $next($request);
    }
}
