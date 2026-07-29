<?php

namespace App\Services\ProductAnalytics;

use App\Models\ProductAnalyticsDaily;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductAnalyticsDashboardService
{
    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     top_features: list<array{feature_key: string, event_count: int}>,
     *     by_kind: list<array{event_kind: string, event_count: int}>,
     *     friction: list<array{event_name: string, feature_key: string, event_count: int}>,
     *     errors: list<array{event_name: string, feature_key: string, event_count: int}>,
     *     error_details: list<array{event_name: string, feature_key: string, event_count: int, dimensions: array<string, string>}>,
     *     bottlenecks: list<array{event_name: string, feature_key: string, event_count: int}>,
     *     daily_trend: list<array{day: string, event_kind: string, event_count: int}>,
     *     backlog_hints: list<array{feature_key: string, used: int, friction: int, errors: int, score: float}>
     * }
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $fromDay = $from->toDateString();
        $toDay = $to->toDateString();

        $base = ProductAnalyticsDaily::query()
            ->whereDate('day', '>=', $fromDay)
            ->whereDate('day', '<=', $toDay);

        $topFeatures = (clone $base)
            ->select('feature_key', DB::raw('SUM(event_count) as event_count'))
            ->where('event_kind', 'used')
            ->groupBy('feature_key')
            ->orderByDesc('event_count')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'feature_key' => (string) $row->feature_key,
                'event_count' => (int) $row->event_count,
            ])
            ->values()
            ->all();

        $byKind = (clone $base)
            ->select('event_kind', DB::raw('SUM(event_count) as event_count'))
            ->groupBy('event_kind')
            ->orderByDesc('event_count')
            ->get()
            ->map(fn ($row) => [
                'event_kind' => (string) $row->event_kind,
                'event_count' => (int) $row->event_count,
            ])
            ->values()
            ->all();

        $friction = $this->topEventsByKind($fromDay, $toDay, 'friction');
        $errors = $this->topEventsByKind($fromDay, $toDay, 'error');
        $bottlenecks = $this->topEventsByKind($fromDay, $toDay, 'performance');

        $dailyTrend = (clone $base)
            ->select('day', 'event_kind', DB::raw('SUM(event_count) as event_count'))
            ->groupBy('day', 'event_kind')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => (string) $row->day,
                'event_kind' => (string) $row->event_kind,
                'event_count' => (int) $row->event_count,
            ])
            ->values()
            ->all();

        return [
            'from' => $fromDay,
            'to' => $toDay,
            'top_features' => $topFeatures,
            'by_kind' => $byKind,
            'friction' => $friction,
            'errors' => $errors,
            'error_details' => $this->errorDetails($fromDay, $toDay),
            'bottlenecks' => $bottlenecks,
            'daily_trend' => $dailyTrend,
            'backlog_hints' => $this->backlogHints($fromDay, $toDay),
        ];
    }

    /**
     * @return list<array{event_name: string, feature_key: string, event_count: int}>
     */
    private function topEventsByKind(string $fromDay, string $toDay, string $kind): array
    {
        return ProductAnalyticsDaily::query()
            ->select('event_name', 'feature_key', DB::raw('SUM(event_count) as event_count'))
            ->whereDate('day', '>=', $fromDay)
            ->whereDate('day', '<=', $toDay)
            ->where('event_kind', $kind)
            ->groupBy('event_name', 'feature_key')
            ->orderByDesc('event_count')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'event_name' => (string) $row->event_name,
                'feature_key' => (string) $row->feature_key,
                'event_count' => (int) $row->event_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Breakdown errori per dimensioni sanitizzate (exception/route/status, senza messaggi/PII).
     *
     * @return list<array{event_name: string, feature_key: string, event_count: int, dimensions: array<string, string>}>
     */
    private function errorDetails(string $fromDay, string $toDay): array
    {
        return ProductAnalyticsDaily::query()
            ->select(
                'event_name',
                'feature_key',
                'dimensions_hash',
                'dimensions',
                DB::raw('SUM(event_count) as event_count')
            )
            ->whereDate('day', '>=', $fromDay)
            ->whereDate('day', '<=', $toDay)
            ->where('event_kind', 'error')
            ->groupBy('event_name', 'feature_key', 'dimensions_hash', 'dimensions')
            ->orderByDesc('event_count')
            ->limit(40)
            ->get()
            ->map(function ($row) {
                $dimensions = is_array($row->dimensions) ? $row->dimensions : [];
                $safe = [];
                foreach ($dimensions as $key => $value) {
                    if (! is_string($key) || (! is_string($value) && ! is_numeric($value))) {
                        continue;
                    }
                    $safe[$key] = (string) $value;
                }

                return [
                    'event_name' => (string) $row->event_name,
                    'feature_key' => (string) $row->feature_key,
                    'event_count' => (int) $row->event_count,
                    'dimensions' => $safe,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Score = friction*2 + errors*3, relative to usage (prioritize pain).
     *
     * @return list<array{feature_key: string, used: int, friction: int, errors: int, score: float}>
     */
    private function backlogHints(string $fromDay, string $toDay): array
    {
        /** @var Collection<string, object> $rows */
        $rows = ProductAnalyticsDaily::query()
            ->select('feature_key', 'event_kind', DB::raw('SUM(event_count) as event_count'))
            ->whereDate('day', '>=', $fromDay)
            ->whereDate('day', '<=', $toDay)
            ->groupBy('feature_key', 'event_kind')
            ->get()
            ->groupBy('feature_key');

        $hints = [];

        foreach ($rows as $featureKey => $group) {
            $used = (int) ($group->firstWhere('event_kind', 'used')?->event_count ?? 0);
            $friction = (int) ($group->firstWhere('event_kind', 'friction')?->event_count ?? 0);
            $errors = (int) ($group->firstWhere('event_kind', 'error')?->event_count ?? 0);

            if ($friction === 0 && $errors === 0) {
                continue;
            }

            $pain = ($friction * 2) + ($errors * 3);
            $score = $used > 0 ? round($pain / max(1, sqrt($used)), 2) : (float) $pain;

            $hints[] = [
                'feature_key' => (string) $featureKey,
                'used' => $used,
                'friction' => $friction,
                'errors' => $errors,
                'score' => $score,
            ];
        }

        usort($hints, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($hints, 0, 10);
    }
}
