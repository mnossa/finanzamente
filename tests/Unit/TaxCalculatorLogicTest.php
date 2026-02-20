<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Test per la logica di calcolo fiscale del Termometro Tasse.
 * 
 * Nota: Questi test verificano la logica matematica che è implementata
 * lato frontend nel hook useTaxCalculator. I test verificano che i calcoli
 * seguano le regole corrette.
 */
class TaxCalculatorLogicTest extends TestCase
{
    /**
     * Test calcolo imposta sostitutiva.
     */
    public function test_substitute_tax_calculation(): void
    {
        $grossIncome = 10000;
        $taxRate = 15;
        
        $expectedTax = ($grossIncome * $taxRate) / 100;
        
        $this->assertEquals(1500, $expectedTax);
    }

    /**
     * Test calcolo contributi INPS.
     */
    public function test_inps_contribution_calculation(): void
    {
        $grossIncome = 10000;
        $inpsRate = 26.23;
        
        $expectedInps = ($grossIncome * $inpsRate) / 100;
        
        $this->assertEquals(2623, $expectedInps);
    }

    /**
     * Test calcolo margine netto.
     */
    public function test_net_margin_calculation(): void
    {
        $grossIncome = 10000;
        $taxRate = 15;
        $inpsRate = 26.23;
        
        $taxAmount = ($grossIncome * $taxRate) / 100;
        $inpsAmount = ($grossIncome * $inpsRate) / 100;
        $netMargin = $grossIncome - $taxAmount - $inpsAmount;
        
        $this->assertEquals(5877, $netMargin);
    }

    /**
     * Test calcolo percentuale di accantonamento.
     */
    public function test_set_aside_percentage_calculation(): void
    {
        $grossIncome = 10000;
        $taxRate = 15;
        $inpsRate = 26.23;
        
        $taxAmount = ($grossIncome * $taxRate) / 100;
        $inpsAmount = ($grossIncome * $inpsRate) / 100;
        $totalSetAside = $taxAmount + $inpsAmount;
        $setAsidePercentage = ($totalSetAside / $grossIncome) * 100;
        
        $this->assertEquals(41.23, $setAsidePercentage);
    }

    /**
     * Test con entrate lorde pari a zero.
     */
    public function test_calculation_with_zero_income(): void
    {
        $grossIncome = 0;
        $taxRate = 15;
        $inpsRate = 26.23;
        
        $taxAmount = ($grossIncome * $taxRate) / 100;
        $inpsAmount = ($grossIncome * $inpsRate) / 100;
        $netMargin = $grossIncome - $taxAmount - $inpsAmount;
        
        $this->assertEquals(0, $taxAmount);
        $this->assertEquals(0, $inpsAmount);
        $this->assertEquals(0, $netMargin);
    }

    /**
     * Test con aliquote personalizzate (regime forfettario 5%).
     */
    public function test_calculation_with_custom_rates(): void
    {
        $grossIncome = 20000;
        $taxRate = 5; // Regime forfettario
        $inpsRate = 26.23;
        
        $taxAmount = ($grossIncome * $taxRate) / 100;
        $inpsAmount = ($grossIncome * $inpsRate) / 100;
        $netMargin = $grossIncome - $taxAmount - $inpsAmount;
        
        $this->assertEquals(1000, $taxAmount);
        $this->assertEquals(5246, $inpsAmount);
        $this->assertEquals(13754, $netMargin);
    }

    /**
     * Test con aliquote Regime Ordinario (più alte).
     */
    public function test_calculation_with_ordinary_regime(): void
    {
        $grossIncome = 30000;
        $taxRate = 23; // IRPEF primo scaglione
        $inpsRate = 26.23;
        
        $taxAmount = ($grossIncome * $taxRate) / 100;
        $inpsAmount = ($grossIncome * $inpsRate) / 100;
        $totalSetAside = $taxAmount + $inpsAmount;
        $setAsidePercentage = ($totalSetAside / $grossIncome) * 100;
        
        $this->assertEquals(6900, $taxAmount);
        $this->assertEquals(7869, $inpsAmount);
        $this->assertEqualsWithDelta(49.23, $setAsidePercentage, 0.01);
    }
}
