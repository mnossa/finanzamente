<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Middleware per rate limiting avanzato e delay progressivo.
 * Logga anche i tentativi per monitoraggio (GDPR compliant: solo IP e timestamp, nessun dato personale).
 */
class AdvancedRateLimitWithDelay
{
    /**
     * Handle an incoming request.
     *
     * @param  int  $maxAttempts
     * @param  int  $decayMinutes
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $maxAttempts = 5, $decayMinutes = 2)
    {

        $ip = $request->ip();
        $salt = env('ADV_THROTTLE_SALT', 'default_salt');
        $ipHash = hash('sha256', $ip.$salt);
        $routeKey = $request->route() && $request->route()->getName()
            ? $request->route()->getName()
            : $request->path();
        $key = 'adv_rate_limit:'.$routeKey.':'.$ipHash;
        $attempts = Cache::get($key, 0);
        $resetAt = Cache::get($key.':reset');
        $now = now()->timestamp;

        // Log hash IP e route/path per monitoraggio (GDPR compliant)
        Log::channel('security')->info('Auth attempt', [
            'ip_hash' => $ipHash,
            'route' => $routeKey,
            'timestamp' => $now,
        ]);

        // Inizializza finestra al primo tentativo
        if (! $resetAt) {
            $resetAt = $now + ($decayMinutes * 60);
            Cache::put($key.':reset', $resetAt, $decayMinutes * 60);
        }

        // Se la finestra è scaduta, riparti da zero
        if ($now >= $resetAt) {
            $attempts = 0;
            $resetAt = $now + ($decayMinutes * 60);
            Cache::put($key.':reset', $resetAt, $decayMinutes * 60);
        }

        if ($attempts >= $maxAttempts) {
            $wait = $resetAt - $now;

            return response()->json([
                'message' => 'Troppi tentativi. Riprova tra '.ceil($wait).' secondi.',
            ], 429);
        }

        // Aggiorna tentativi e applica delay progressivo
        Cache::put($key, $attempts + 1, $decayMinutes * 60);
        if ($attempts > 0) {
            // Delay progressivo: 1s per tentativo oltre il primo, max 5s
            sleep(min($attempts, 5));
        }

        return $next($request);
    }
}
