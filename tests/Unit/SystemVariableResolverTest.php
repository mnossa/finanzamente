<?php

namespace Tests\Unit;

use App\Services\SystemVariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemVariableResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_exposes_usage_examples_in_metadata(): void
    {
        $metadata = app(SystemVariableResolver::class)->listMetadata();

        $periodExpenses = collect($metadata)->firstWhere('code', 'period_expenses');
        $daysElapsed = collect($metadata)->firstWhere('code', 'days_elapsed_in_month');

        $this->assertNotNull($periodExpenses);
        $this->assertSame('[period_expenses] / [days_elapsed_in_month]', $periodExpenses['example']);
        $this->assertNotNull($daysElapsed);
        $this->assertStringContainsString('[days_elapsed_in_month]', (string) $daysElapsed['example']);
    }

    #[Test]
    public function it_keeps_retired_partita_iva_tax_variables_out_of_formula_palette(): void
    {
        $codes = collect(app(SystemVariableResolver::class)->listMetadata())->pluck('code');

        $this->assertFalse($codes->contains('inps_amount'));
        $this->assertFalse($codes->contains('estimated_taxes'));
        $this->assertFalse($codes->contains('flat_tax_amount'));
    }
}
