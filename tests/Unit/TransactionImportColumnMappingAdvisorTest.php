<?php

namespace Tests\Unit;

use App\Services\TransactionImportColumnMappingAdvisor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionImportColumnMappingAdvisorTest extends TestCase
{
    #[Test]
    public function it_warns_when_two_fields_map_to_same_column(): void
    {
        $warnings = TransactionImportColumnMappingAdvisor::warnings([
            'date' => 0,
            'amount' => 1,
            'description' => 0,
        ], ['Data', 'Importo', 'Descrizione']);

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('stessa colonna', $warnings[0]);
        $this->assertStringContainsString('Data', $warnings[0]);
        $this->assertStringContainsString('Descrizione', $warnings[0]);
    }

    #[Test]
    public function it_warns_when_description_column_header_looks_like_category(): void
    {
        $warnings = TransactionImportColumnMappingAdvisor::warnings([
            'date' => 0,
            'amount' => 1,
            'description' => 2,
        ], ['Data', 'Importo', 'Categoria']);

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Categoria', $warnings[0]);
        $this->assertStringContainsString('Descrizione', $warnings[0]);
    }

    #[Test]
    public function it_returns_empty_when_mapping_is_consistent(): void
    {
        $warnings = TransactionImportColumnMappingAdvisor::warnings([
            'date' => 0,
            'amount' => 1,
            'description' => 2,
            'category' => 3,
        ], ['Data', 'Importo', 'Descrizione', 'Categoria']);

        $this->assertSame([], $warnings);
    }
}
