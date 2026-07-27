<?php

namespace App\Http\Middleware;

use App\Services\ProductAnalytics\ProductAnalyticsRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra aggregati privacy-safe per richieste lente (solo route name + bucket ms).
 */
class RecordSlowProductAnalytics
{
    public function __construct(private readonly ProductAnalyticsRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        if (! config('product_analytics.slow_request_enabled', true)) {
            return $response;
        }

        $elapsedMs = (int) round((microtime(true) - $started) * 1000);
        $threshold = (int) config('product_analytics.slow_request_ms', 1500);

        if ($elapsedMs < $threshold) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        if (! is_string($routeName) || $routeName === '') {
            return $response;
        }

        // Skip ops ingest / telescope / health to avoid noise.
        if (str_starts_with($routeName, 'product-analytics.')
            || str_starts_with($routeName, 'telescope.')
            || $routeName === 'up') {
            return $response;
        }

        $bucket = match (true) {
            $elapsedMs >= 5000 => '5000plus',
            $elapsedMs >= 3000 => '3000_4999',
            $elapsedMs >= 2000 => '2000_2999',
            default => '1500_1999',
        };

        $this->recorder->record('route.slow', [
            'feature' => 'performance',
            'route' => substr(preg_replace('/[^a-z0-9._-]/', '', strtolower($routeName)) ?? '', 0, 64),
            'ms_bucket' => $bucket,
            'status' => $response->getStatusCode() >= 500 ? '5xx' : ($response->getStatusCode() >= 400 ? '4xx' : '2xx'),
        ]);

        return $response;
    }
}
