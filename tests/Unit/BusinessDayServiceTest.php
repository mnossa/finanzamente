<?php

namespace Tests\Unit;

use App\Services\BusinessDayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BusinessDayServiceTest extends TestCase
{
    use RefreshDatabase;

    private BusinessDayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BusinessDayService::class);
    }

    #[Test]
    public function saturday_is_not_working_day(): void
    {
        $saturday = Carbon::parse('2026-05-16'); // sabato
        $this->assertFalse($this->service->isWorkingDay($saturday));
    }

    #[Test]
    public function adjusts_saturday_to_monday(): void
    {
        $saturday = Carbon::parse('2026-05-16');
        $adjusted = $this->service->adjustToNextWorkingDay($saturday);
        $this->assertTrue($adjusted->isMonday());
        $this->assertSame('2026-05-18', $adjusted->toDateString());
    }

    #[Test]
    public function christmas_is_holiday(): void
    {
        $christmas = Carbon::parse('2026-12-25');
        $this->assertFalse($this->service->isWorkingDay($christmas));
    }

    #[Test]
    public function adjusts_christmas_to_next_working_day(): void
    {
        $christmas = Carbon::parse('2026-12-25'); // venerdì festivo
        $adjusted = $this->service->adjustToNextWorkingDay($christmas);
        $this->assertTrue($this->service->isWorkingDay($adjusted));
        $this->assertGreaterThan($christmas->toDateString(), $adjusted->toDateString());
    }
}
