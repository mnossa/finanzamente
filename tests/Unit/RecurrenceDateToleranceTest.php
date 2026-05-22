<?php

namespace Tests\Unit;

use App\Support\RecurrenceDateTolerance;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RecurrenceDateToleranceTest extends TestCase
{
    #[Test]
    public function monthly_and_yearly_windows_are_seven_days(): void
    {
        $this->assertSame(7, RecurrenceDateTolerance::windowDaysForFrequency('monthly'));
        $this->assertSame(7, RecurrenceDateTolerance::windowDaysForFrequency('yearly'));
    }

    #[Test]
    public function find_matching_slot_within_window(): void
    {
        $expected = [
            Carbon::parse('2025-01-05'),
            Carbon::parse('2025-02-05'),
        ];

        $index = RecurrenceDateTolerance::findMatchingExpectedSlotIndex(
            Carbon::parse('2025-02-07'),
            $expected,
            'monthly',
        );

        $this->assertSame(1, $index);
    }

    #[Test]
    public function project_occurrences_between_dates(): void
    {
        $occurrences = RecurrenceDateTolerance::projectOccurrencesBetween(
            Carbon::parse('2025-01-05'),
            Carbon::parse('2025-03-05'),
            'monthly',
        );

        $this->assertCount(3, $occurrences);
        $this->assertSame('2025-02-05', $occurrences[1]->toDateString());
    }
}
