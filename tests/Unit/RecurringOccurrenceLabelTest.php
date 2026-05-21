<?php

namespace Tests\Unit;

use App\Support\RecurringOccurrenceLabel;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RecurringOccurrenceLabelTest extends TestCase
{
    #[Test]
    public function monthly_suffix_uses_italian_month_and_year(): void
    {
        $date = Carbon::parse('2026-03-15');
        $result = RecurringOccurrenceLabel::buildDescriptionWithOccurrence('Abbonamento Netflix', $date, 'monthly');

        $this->assertSame('Abbonamento Netflix - Marzo 2026', $result);
    }

    #[Test]
    public function does_not_duplicate_suffix_when_already_present(): void
    {
        $date = Carbon::parse('2026-03-15');
        $existing = 'Abbonamento Netflix - Marzo 2026';
        $result = RecurringOccurrenceLabel::buildDescriptionWithOccurrence($existing, $date, 'monthly');

        $this->assertSame($existing, $result);
    }

    #[Test]
    public function daily_suffix_uses_short_date(): void
    {
        $date = Carbon::parse('2026-05-21');
        $result = RecurringOccurrenceLabel::buildDescriptionWithOccurrence(null, $date, 'daily');

        $this->assertSame('21/05/2026', $result);
    }
}
