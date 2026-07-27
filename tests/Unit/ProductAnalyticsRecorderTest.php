<?php

namespace Tests\Unit;

use App\Models\ProductAnalyticsDaily;
use App\Services\ProductAnalytics\ProductAnalyticsRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAnalyticsRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitize_strips_blocked_pii_keys_and_keeps_safe_enums(): void
    {
        $recorder = app(ProductAnalyticsRecorder::class);

        $clean = $recorder->sanitizeDimensions([
            'email' => 'user@example.com',
            'user_id' => 42,
            'amount' => 12.5,
            'type' => 'expense',
            'has_tags' => true,
            'form_seconds' => 12,
            'description' => 'spesa supermercato',
        ]);

        $this->assertSame([
            'form_seconds' => 12,
            'has_tags' => true,
            'type' => 'expense',
        ], $clean);
        $this->assertFalse($recorder->containsBlockedKeys($clean));
    }

    public function test_record_upserts_daily_aggregates_without_storing_raw_pii(): void
    {
        $recorder = app(ProductAnalyticsRecorder::class);

        $recorder->record('transaction.created', [
            'type' => 'expense',
            'email' => 'leak@example.com',
            'user_id' => 99,
        ]);
        $recorder->record('transaction.created', [
            'type' => 'expense',
            'email' => 'other@example.com',
        ]);

        $this->assertDatabaseCount('product_analytics_daily', 1);
        $this->assertDatabaseHas('product_analytics_daily', [
            'event_name' => 'transaction.created',
            'event_kind' => 'used',
            'feature_key' => 'transaction',
            'event_count' => 2,
        ]);

        $row = ProductAnalyticsDaily::query()->first();
        $this->assertIsArray($row->dimensions);
        $this->assertArrayNotHasKey('email', $row->dimensions);
        $this->assertArrayNotHasKey('user_id', $row->dimensions);
        $this->assertSame('expense', $row->dimensions['type']);
    }

    public function test_friction_and_performance_kinds_are_detected(): void
    {
        $recorder = app(ProductAnalyticsRecorder::class);

        $this->assertSame('friction', $recorder->resolveEventKind('form.abandoned'));
        $this->assertSame('error', $recorder->resolveEventKind('feature.error'));
        $this->assertSame('performance', $recorder->resolveEventKind('route.slow'));
        $this->assertSame('used', $recorder->resolveEventKind('nav.bottom_bar'));
    }
}
