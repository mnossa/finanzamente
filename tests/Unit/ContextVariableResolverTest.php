<?php

namespace Tests\Unit;

use App\Services\ContextVariableResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContextVariableResolverTest extends TestCase
{
    use RefreshDatabase;

    private ContextVariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(ContextVariableResolver::class);
    }

    #[Test]
    public function it_resolves_calendar_fields_from_period_end(): void
    {
        $start = Carbon::create(2026, 3, 1)->startOfDay();
        $end = Carbon::create(2026, 3, 15)->endOfDay();

        $this->assertSame(2026.0, $this->resolver->resolve($start, $end, 'year'));
        $this->assertSame(3.0, $this->resolver->resolve($start, $end, 'month'));
        $this->assertSame(15.0, $this->resolver->resolve($start, $end, 'day'));
        $this->assertSame(74.0, $this->resolver->resolve($start, $end, 'day_of_year'));
        $this->assertSame(1.0, $this->resolver->resolve($start, $end, 'quarter'));
        $this->assertSame(31.0, $this->resolver->resolve($start, $end, 'days_in_month'));
        $this->assertSame(365.0, $this->resolver->resolve($start, $end, 'days_in_year'));
        $this->assertSame(15.0, $this->resolver->resolve($start, $end, 'days_elapsed_in_month'));
        $this->assertSame(16.0, $this->resolver->resolve($start, $end, 'days_remaining_in_month'));
        $this->assertSame(74.0, $this->resolver->resolve($start, $end, 'days_elapsed_in_year'));
        $this->assertSame(291.0, $this->resolver->resolve($start, $end, 'days_remaining_in_year'));
    }

    #[Test]
    public function it_resolves_days_in_period_inclusively(): void
    {
        $start = Carbon::create(2026, 6, 1)->startOfDay();
        $end = Carbon::create(2026, 6, 10)->endOfDay();

        $this->assertSame(10.0, $this->resolver->resolve($start, $end, 'days_in_period'));
    }

    #[Test]
    public function it_counts_single_day_period_as_one(): void
    {
        $day = Carbon::create(2026, 6, 10)->startOfDay();

        $this->assertSame(1.0, $this->resolver->resolve($day, $day->copy()->endOfDay(), 'days_in_period'));
    }
}
