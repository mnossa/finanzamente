<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware che verifica se l'utente ha una household attiva.
 * Se non ce l'ha, lo reindirizza alla pagina di selezione/creazione household.
 */
class EnsureHasActiveHousehold
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

        // Se l'utente non ha una household attiva, reindirizza alla selezione
        if (!$user->active_household_id) {
            // Controlla se ha almeno una household
            $householdsCount = $user->households()->count();
            
            if ($householdsCount === 0) {
                // Nessuna household: vai alla creazione
                return redirect()->route('households.create');
            }
            
            // Ha household ma nessuna attiva: vai alla selezione
            return redirect()->route('households.select');
        }

        return $next($request);
    }
}
