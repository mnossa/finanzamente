<?php

namespace App\Services\ProductAnalytics;

use App\Models\ProductAnalyticsDaily;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProductAnalyticsRecorder
{
    /**
     * Record one privacy-safe product event into daily aggregates.
     * Never stores user_id, email, IP, amounts, or free-text payloads.
     *
     * @param  array<string, mixed>  $dimensions
     */
    public function record(string $eventName, array $dimensions = [], ?Carbon $day = null, int $count = 1): void
    {
        if (! config('product_analytics.enabled', true)) {
            return;
        }

        $eventName = $this->normalizeEventName($eventName);
        if ($eventName === '') {
            return;
        }

        $count = max(1, min(1000, $count));
        $safeDimensions = $this->sanitizeDimensions($dimensions);
        $featureKey = $this->resolveFeatureKey($eventName, $safeDimensions);
        $eventKind = $this->resolveEventKind($eventName);
        $dimensionsHash = $this->hashDimensions($safeDimensions);
        $dayValue = ($day ?? now())->toDateString();
        $payload = [
            'day' => $dayValue,
            'event_kind' => $eventKind,
            'feature_key' => $featureKey,
            'event_name' => $eventName,
            'dimensions_hash' => $dimensionsHash,
        ];

        $updated = ProductAnalyticsDaily::query()
            ->where($payload)
            ->increment('event_count', $count);

        if ($updated > 0) {
            return;
        }

        try {
            ProductAnalyticsDaily::query()->create([
                ...$payload,
                'dimensions' => $safeDimensions === [] ? null : $safeDimensions,
                'event_count' => $count,
            ]);
        } catch (\Throwable) {
            ProductAnalyticsDaily::query()
                ->where($payload)
                ->increment('event_count', $count);
        }
    }

    /**
     * @param  list<array{name?: mixed, data?: mixed, count?: mixed}>  $events
     * @return int Number of events accepted
     */
    public function recordMany(array $events): int
    {
        $max = (int) config('product_analytics.max_events_per_request', 20);
        $accepted = 0;

        foreach (array_slice($events, 0, $max) as $event) {
            if (! is_array($event)) {
                continue;
            }

            $name = is_string($event['name'] ?? null) ? $event['name'] : '';
            if ($name === '') {
                continue;
            }

            $data = is_array($event['data'] ?? null) ? $event['data'] : [];
            $count = is_numeric($event['count'] ?? null) ? (int) $event['count'] : 1;
            $this->record($name, $data, null, $count);
            $accepted++;
        }

        return $accepted;
    }

    public function normalizeEventName(string $eventName): string
    {
        $eventName = Str::lower(trim($eventName));
        $eventName = preg_replace('/[^a-z0-9._-]/', '', $eventName) ?? '';

        return Str::limit($eventName, 128, '');
    }

    /**
     * @param  array<string, mixed>  $dimensions
     * @return array<string, bool|int|string>
     */
    public function sanitizeDimensions(array $dimensions): array
    {
        $blocked = array_map('strtolower', config('product_analytics.blocked_dimension_keys', []));
        $maxKeys = (int) config('product_analytics.max_dimension_keys', 8);
        $clean = [];

        foreach ($dimensions as $key => $value) {
            if (count($clean) >= $maxKeys) {
                break;
            }

            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $normalizedKey = Str::lower(preg_replace('/[^a-z0-9_]/', '', (string) $key) ?? '');
            if ($normalizedKey === '' || in_array($normalizedKey, $blocked, true)) {
                continue;
            }

            if (is_bool($value)) {
                $clean[$normalizedKey] = $value;

                continue;
            }

            if (is_int($value) || (is_float($value) && floor($value) === $value)) {
                $clean[$normalizedKey] = (int) max(-1_000_000, min(1_000_000, (int) $value));

                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
                // Reject emails / long free text; keep short enums only.
                if ($value === '' || strlen($value) > 64 || str_contains($value, '@') || preg_match('/\s{2,}/', $value)) {
                    continue;
                }
                $safe = preg_replace('/[^a-zA-Z0-9._:-]/', '', $value) ?? '';
                if ($safe !== '') {
                    $clean[$normalizedKey] = Str::limit($safe, 64, '');
                }
            }
        }

        ksort($clean);

        return $clean;
    }

    /**
     * @param  array<string, bool|int|string>  $dimensions
     */
    public function resolveFeatureKey(string $eventName, array $dimensions): string
    {
        if (isset($dimensions['feature']) && is_string($dimensions['feature'])) {
            return Str::limit($dimensions['feature'], 64, '');
        }

        if (isset($dimensions['form']) && is_string($dimensions['form'])) {
            $parts = explode('.', $dimensions['form']);

            return Str::limit($parts[0] !== '' ? $parts[0] : 'form', 64, '');
        }

        $parts = explode('.', $eventName);

        return Str::limit($parts[0] !== '' ? $parts[0] : 'unknown', 64, '');
    }

    public function resolveEventKind(string $eventName): string
    {
        if (in_array($eventName, config('product_analytics.performance_events', []), true)) {
            return 'performance';
        }

        if (in_array($eventName, config('product_analytics.error_events', []), true)) {
            return 'error';
        }

        foreach (config('product_analytics.error_prefixes', []) as $prefix) {
            if (str_starts_with($eventName, $prefix)) {
                return 'error';
            }
        }

        if (in_array($eventName, config('product_analytics.friction_events', []), true)) {
            return 'friction';
        }

        foreach (config('product_analytics.friction_prefixes', []) as $prefix) {
            if (str_starts_with($eventName, $prefix)) {
                return 'friction';
            }
        }

        if ($eventName === 'feature.used' || str_starts_with($eventName, 'feature.used')) {
            return 'used';
        }

        return 'used';
    }

    /**
     * @param  array<string, bool|int|string>  $dimensions
     */
    public function hashDimensions(array $dimensions): string
    {
        if ($dimensions === []) {
            return '';
        }

        return hash('sha256', json_encode($dimensions, JSON_THROW_ON_ERROR));
    }

    /**
     * Assert payload would not contain blocked keys (for tests / admin checks).
     *
     * @param  array<string, mixed>  $payload
     */
    public function containsBlockedKeys(array $payload): bool
    {
        $blocked = array_map('strtolower', config('product_analytics.blocked_dimension_keys', []));

        foreach (array_keys($payload) as $key) {
            $normalized = Str::lower(preg_replace('/[^a-z0-9_]/', '', (string) $key) ?? '');
            if (in_array($normalized, $blocked, true)) {
                return true;
            }
        }

        return false;
    }
}
