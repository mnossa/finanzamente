<?php

namespace Tests\Unit;

use App\Services\TransactionImportService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionImportServiceTest extends TestCase
{
    private TransactionImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionImportService();
    }

    #[Test]
    public function it_parses_italian_amount_with_dot_thousands_comma_decimal(): void
    {
        $this->assertEquals(1234.56, $this->service->parseAmount('1.234,56'));
    }

    #[Test]
    public function it_parses_negative_italian_amount(): void
    {
        $this->assertEquals(-1234.56, $this->service->parseAmount('-1.234,56'));
    }

    #[Test]
    public function it_parses_amount_with_only_comma_decimal(): void
    {
        $this->assertEquals(100.50, $this->service->parseAmount('100,50'));
    }

    #[Test]
    public function it_parses_english_amount(): void
    {
        $this->assertEquals(1234.56, $this->service->parseAmount('1,234.56'));
    }

    #[Test]
    public function it_returns_null_for_invalid_amount(): void
    {
        $this->assertNull($this->service->parseAmount('not-a-number'));
    }

    #[Test]
    public function it_returns_null_for_empty_amount(): void
    {
        $this->assertNull($this->service->parseAmount(''));
    }

    #[Test]
    public function it_parses_csv_with_semicolon_delimiter(): void
    {
        $csv = "Data;Descrizione;Importo\n01/01/2024;Supermercato;-50,00\n05/01/2024;Stipendio;1500,00\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => ['date' => 0, 'description' => 1, 'amount' => 2, 'notes' => null],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(2, $rows);
        $this->assertEquals('2024-01-01', $rows[0]['date']);
        $this->assertEquals('Supermercato', $rows[0]['description']);
        $this->assertEquals(-50.0, $rows[0]['amount']);
        $this->assertEquals('2024-01-05', $rows[1]['date']);
        $this->assertEquals(1500.0, $rows[1]['amount']);
    }

    #[Test]
    public function it_marks_rows_with_invalid_date_as_errors(): void
    {
        $csv = "Data;Descrizione;Importo\nNOT-A-DATE;Test;100,00\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => ['date' => 0, 'description' => 1, 'amount' => 2, 'notes' => null],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(1, $rows);
        $this->assertNotEmpty($rows[0]['errors']);
        $this->assertNull($rows[0]['date']);
    }

    #[Test]
    public function it_marks_rows_with_missing_description_as_errors(): void
    {
        $csv = "Data;Descrizione;Importo\n01/01/2024;;100,00\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => ['date' => 0, 'description' => 1, 'amount' => 2, 'notes' => null],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(1, $rows);
        // La descrizione vuota genera un warning (la riga rimane importabile)
        $this->assertEmpty($rows[0]['errors']);
        $this->assertNotEmpty($rows[0]['warnings']);
    }

    #[Test]
    public function it_validates_rows_separating_valid_from_invalid(): void
    {
        $rows = [
            ['date' => '2024-01-01', 'amount' => 100.0, 'description' => 'Test', 'errors' => []],
            ['date' => null, 'amount' => null, 'description' => '', 'errors' => ['Riga 2: data mancante']],
        ];

        $result = $this->service->validateRows($rows);

        $this->assertCount(1, $result['valid']);
        $this->assertCount(1, $result['invalid']);
    }

    #[Test]
    public function it_parses_csv_without_header(): void
    {
        $csv = "01/01/2024;Supermercato;-50,00\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => false,
            'encoding' => 'UTF-8',
            'column_mapping' => ['date' => 0, 'description' => 1, 'amount' => 2, 'notes' => null],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(1, $rows);
        $this->assertEquals('2024-01-01', $rows[0]['date']);
    }

    #[Test]
    public function it_parses_amount_with_euro_symbol(): void
    {
        $this->assertEquals(100.0, $this->service->parseAmount('€ 100,00'));
    }

    #[Test]
    public function it_parses_amount_with_multiple_commas_as_thousands(): void
    {
        $this->assertEquals(1234567.0, $this->service->parseAmount('1,234,567'));
    }

    #[Test]
    public function it_parses_amount_with_single_comma_thousands(): void
    {
        $this->assertEquals(1234.0, $this->service->parseAmount('1,234'));
    }
}
