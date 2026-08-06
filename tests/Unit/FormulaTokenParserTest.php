<?php

namespace Tests\Unit;

use App\Support\FormulaTokenParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaTokenParserTest extends TestCase
{
    use RefreshDatabase;

    private FormulaTokenParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new FormulaTokenParser;
    }

    #[Test]
    public function it_extracts_unique_variable_tokens(): void
    {
        $tokens = $this->parser->extract('[total_income] - [custom_tax] + [total_income]');

        $this->assertSame(['total_income', 'custom_tax'], $tokens);
    }

    #[Test]
    public function it_sanitizes_display_names_to_slug_codes(): void
    {
        $this->assertSame('my_tax_2026', $this->parser->sanitizeCode('My Tax 2026'));
    }

    #[Test]
    public function it_rejects_invalid_codes(): void
    {
        $this->expectException(ValidationException::class);

        $this->parser->sanitizeCode('!!!');
    }

    #[Test]
    public function it_substitutes_resolved_values(): void
    {
        $result = $this->parser->substitute(
            '([total_income] * 0.1) - [custom_tax]',
            ['total_income' => 1000, 'custom_tax' => 50],
        );

        $this->assertSame('(1000 * 0.1) - 50', $result);
    }
}
