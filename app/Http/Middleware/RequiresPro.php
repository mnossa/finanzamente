<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware che protegge le rotte riservate al piano Pro.
 *
 * Per richieste Inertia: reindirizza alla pagina di upgrade con messaggio flash.
 * Per richieste JSON/API: restituisce 403.
 */
class RequiresPro
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isPro()) {
            return $next($request);
        }

        if ($request->wantsJson() && ! $request->inertia()) {
            return response()->json([
                'message' => 'Questa funzionalità è disponibile solo nel piano Pro.',
            ], 403);
        }

        return redirect()->route('profile.subscription')
            ->with('info', 'Questa funzionalità è disponibile solo nel piano Pro. Esegui l\'upgrade per sbloccarla.');
    }
}
