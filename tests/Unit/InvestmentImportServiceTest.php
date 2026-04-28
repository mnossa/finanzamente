<?php

namespace Tests\Unit;

use App\Services\InvestmentImportService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InvestmentImportServiceTest extends TestCase
{
    private InvestmentImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvestmentImportService;
    }

    // ─── parseDecimal ────────────────────────────────────────────────────────

    #[Test]
    public function it_parses_italian_decimal_with_dot_thousands_comma_decimal(): void
    {
        $this->assertEquals(1234.56, $this->service->parseDecimal('1.234,56'));
    }

    #[Test]
    public function it_parses_decimal_with_comma_only(): void
    {
        $this->assertEquals(180.50, $this->service->parseDecimal('180,50'));
    }

    #[Test]
    public function it_parses_english_decimal_format(): void
    {
        $this->assertEquals(1234.56, $this->service->parseDecimal('1,234.56'));
    }

    #[Test]
    public function it_parses_plain_integer(): void
    {
        $this->assertEquals(10.0, $this->service->parseDecimal('10'));
    }

    #[Test]
    public function it_parses_high_precision_decimal(): void
    {
        $this->assertEquals(0.00012345, $this->service->parseDecimal('0.00012345'));
    }

    #[Test]
    public function it_returns_null_for_invalid_decimal(): void
    {
        $this->assertNull($this->service->parseDecimal('not-a-number'));
    }

    #[Test]
    public function it_returns_null_for_empty_decimal(): void
    {
        $this->assertNull($this->service->parseDecimal(''));
    }

    // ─── parseCsv ────────────────────────────────────────────────────────────

    #[Test]
    public function it_parses_investment_csv_with_ticker(): void
    {
        $csv = "Data;Ticker;Quantità;Prezzo\n01/01/2024;AAPL;10;180,50\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'buy_date' => 0,
                'ticker' => 1,
                'quantity' => 2,
                'buy_price' => 3,
            ],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(1, $rows);
        $this->assertEquals('2024-01-01', $rows[0]['buy_date']);
        $this->assertEquals('AAPL', $rows[0]['ticker']);
        $this->assertEquals(10.0, $rows[0]['quantity']);
        $this->assertEquals(180.50, $rows[0]['buy_price']);
        $this->assertEmpty($rows[0]['errors']);
    }

    #[Test]
    public function it_parses_investment_csv_with_isin(): void
    {
        $csv = "Data;ISIN;Qty;Price\n15/03/2024;US0378331005;2;190,00\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'buy_date' => 0,
                'isin' => 1,
                'quantity' => 2,
                'buy_price' => 3,
            ],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(1, $rows);
        $this->assertEquals('US0378331005', $rows[0]['isin']);
        $this->assertNull($rows[0]['ticker']);
        $this->assertEmpty($rows[0]['errors']);
    }

    #[Test]
    public function it_marks_rows_with_missing_ticker_and_isin_as_errors(): void
    {
        $csv = "Data;Qty;Price\n01/01/2024;5;50,00\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'buy_date' => 0,
                'quantity' => 1,
                'buy_price' => 2,
            ],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(1, $rows);
        $this->assertNotEmpty($rows[0]['errors']);
        $this->assertStringContainsString('ticker o ISIN', $rows[0]['errors'][0]);
    }

    #[Test]
    public function it_marks_rows_with_invalid_date_as_errors(): void
    {
        $csv = "Data;Ticker;Qty;Price\nNOT-A-DATE;AAPL;5;50,00\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'buy_date' => 0,
                'ticker' => 1,
                'quantity' => 2,
                'buy_price' => 3,
            ],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(1, $rows);
        $this->assertNotEmpty($rows[0]['errors']);
        $this->assertNull($rows[0]['buy_date']);
    }

    #[Test]
    public function it_marks_rows_with_invalid_quantity_as_errors(): void
    {
        $csv = "Data;Ticker;Qty;Price\n01/01/2024;AAPL;-5;50,00\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'buy_date' => 0,
                'ticker' => 1,
                'quantity' => 2,
                'buy_price' => 3,
            ],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(1, $rows);
        $this->assertNotEmpty($rows[0]['errors']);
        $this->assertNull($rows[0]['quantity']);
    }

    #[Test]
    public function it_parses_fees_from_csv(): void
    {
        $csv = "Data;Ticker;Qty;Price;Fees\n01/01/2024;AAPL;10;180,50;5,00\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'buy_date' => 0,
                'ticker' => 1,
                'quantity' => 2,
                'buy_price' => 3,
                'fees' => 4,
            ],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(1, $rows);
        $this->assertEquals(5.0, $rows[0]['fees']);
    }

    #[Test]
    public function it_parses_csv_without_header(): void
    {
        $csv = "01/01/2024;AAPL;10;180,50\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => false,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'buy_date' => 0,
                'ticker' => 1,
                'quantity' => 2,
                'buy_price' => 3,
            ],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertCount(1, $rows);
        $this->assertEquals('2024-01-01', $rows[0]['buy_date']);
    }

    // ─── validateRows ────────────────────────────────────────────────────────

    #[Test]
    public function it_validates_rows_separating_valid_from_invalid(): void
    {
        $rows = [
            [
                'buy_date' => '2024-01-01',
                'quantity' => 10.0,
                'buy_price' => 180.50,
                'ticker' => 'AAPL',
                'isin' => null,
                'errors' => [],
            ],
            [
                'buy_date' => null,
                'quantity' => null,
                'buy_price' => null,
                'ticker' => null,
                'isin' => null,
                'errors' => ['Riga 2: data di acquisto mancante'],
            ],
        ];

        $result = $this->service->validateRows($rows);

        $this->assertCount(1, $result['valid']);
        $this->assertCount(1, $result['invalid']);
    }

    #[Test]
    public function it_requires_ticker_or_isin_to_be_valid(): void
    {
        $rows = [
            [
                'buy_date' => '2024-01-01',
                'quantity' => 10.0,
                'buy_price' => 180.50,
                'ticker' => null,
                'isin' => null,
                'errors' => ['Riga 1: ticker o ISIN obbligatorio'],
            ],
        ];

        $result = $this->service->validateRows($rows);

        $this->assertCount(0, $result['valid']);
        $this->assertCount(1, $result['invalid']);
    }

    #[Test]
    public function it_accepts_row_with_only_isin(): void
    {
        $rows = [
            [
                'buy_date' => '2024-01-01',
                'quantity' => 10.0,
                'buy_price' => 180.50,
                'ticker' => null,
                'isin' => 'US0378331005',
                'errors' => [],
            ],
        ];

        $result = $this->service->validateRows($rows);

        $this->assertCount(1, $result['valid']);
    }

    #[Test]
    public function it_converts_ticker_to_uppercase(): void
    {
        $csv = "Data;Ticker;Qty;Price\n01/01/2024;aapl;10;180,50\n";
        $layout = [
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'buy_date' => 0,
                'ticker' => 1,
                'quantity' => 2,
                'buy_price' => 3,
            ],
        ];

        $rows = $this->service->parseCsv($csv, $layout);

        $this->assertEquals('AAPL', $rows[0]['ticker']);
    }
}
