<?php

namespace App\Services\ProductAnalytics;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registra eccezioni server come aggregati privacy-safe (niente messaggi/PII).
 */
class ProductAnalyticsExceptionRecorder
{
    public function __construct(private readonly ProductAnalyticsRecorder $recorder) {}

    public function record(Throwable $e, ?Request $request = null): void
    {
        if (! config('product_analytics.enabled', true)) {
            return;
        }

        $request ??= request();
        $routeName = $request?->route()?->getName();
        $route = is_string($routeName) && $routeName !== ''
            ? substr(preg_replace('/[^a-z0-9._-]/', '', strtolower($routeName)) ?? '', 0, 64)
            : 'unknown';

        $feature = explode('.', $route)[0] ?: 'unknown';
        $exceptionClass = Str::of(class_basename($e))->limit(64, '')->toString();
        $status = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 500;
        if ($status < 400) {
            $status = 500;
        }

        $this->recorder->record('exception.server', [
            'feature' => $feature,
            'route' => $route,
            'exception' => $exceptionClass,
            'status' => (string) $status,
        ]);
    }
}
