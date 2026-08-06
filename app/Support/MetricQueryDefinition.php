<?php

namespace App\Support;

/**
 * Value object per chart_config.metric_query.
 *
 * @phpstan-type MetricFilter array{
 *   field: string,
 *   operator: string,
 *   value?: mixed,
 *   runtime_key?: string|null
 * }
 */
class MetricQueryDefinition
{
    /**
     * @param  array<int, array<string, mixed>>  $filters
     */
    public function __construct(
        public readonly string $datasource,
        public readonly string $measure,
        public readonly string $amountField,
        public readonly array $filters,
    ) {}

    /**
     * @param  array<string, mixed>|null  $raw
     */
    public static function fromChartConfig(?array $raw): ?self
    {
        if ($raw === null || ! is_array($raw) || ! isset($raw['datasource'], $raw['measure'])) {
            return null;
        }

        $datasource = (string) $raw['datasource'];
        $measure = (string) $raw['measure'];
        $amountField = (string) ($raw['amount_field'] ?? config('metric_queries.default_amount_field', 'amount_base'));
        $filters = is_array($raw['filters'] ?? null) ? $raw['filters'] : [];

        return new self($datasource, $measure, $amountField, $filters);
    }

    public function requiresPeriod(): bool
    {
        $meta = config("metric_queries.datasources.{$this->datasource}");

        return (bool) ($meta['requires_period'] ?? true);
    }
}
