<?php

namespace Tests\Unit;

use App\Models\ProductAnalyticsDaily;
use App\Services\ProductAnalytics\ProductAnalyticsExceptionRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProductAnalyticsExceptionRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_exception_class_and_route_without_message(): void
    {
        $request = Request::create('/investimenti/1', 'GET');
        $request->setRouteResolver(function () {
            $route = new Route(['GET'], '/investimenti/{investment}', fn () => null);
            $route->name('investments.show');

            return $route;
        });

        app(ProductAnalyticsExceptionRecorder::class)->record(
            new \RuntimeException('secret email user@example.com and amount 1234'),
            $request
        );

        $this->assertDatabaseHas('product_analytics_daily', [
            'event_name' => 'exception.server',
            'event_kind' => 'error',
            'feature_key' => 'investments',
        ]);

        $row = ProductAnalyticsDaily::query()->first();
        $this->assertSame('investments.show', $row->dimensions['route']);
        $this->assertSame('RuntimeException', $row->dimensions['exception']);
        $this->assertStringNotContainsString('user@example.com', json_encode($row->dimensions));
        $this->assertStringNotContainsString('1234', json_encode($row->dimensions));
    }

    public function test_http_exception_keeps_status_code(): void
    {
        $request = Request::create('/x', 'GET');
        $request->setRouteResolver(function () {
            $route = new Route(['GET'], '/x', fn () => null);
            $route->name('dashboard');

            return $route;
        });

        app(ProductAnalyticsExceptionRecorder::class)->record(
            new HttpException(503, 'unavailable'),
            $request
        );

        $row = ProductAnalyticsDaily::query()->first();
        $this->assertSame('503', $row->dimensions['status']);
    }
}
