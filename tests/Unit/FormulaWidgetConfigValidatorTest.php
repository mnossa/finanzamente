<?php

namespace Tests\Unit;

use App\Models\FormulaWidget;
use App\Services\FormulaWidgetConfigValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetConfigValidatorTest extends TestCase
{
    use RefreshDatabase;

    private FormulaWidgetConfigValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(FormulaWidgetConfigValidator::class);
    }

    #[Test]
    public function it_requires_two_series_for_bar_charts(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(FormulaWidget::DISPLAY_BAR, null, [
            'series' => [
                ['code' => 'total_income', 'label' => 'Entrate'],
            ],
        ]);
    }

    #[Test]
    public function it_requires_progress_codes(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validate(FormulaWidget::DISPLAY_PROGRESS, 'calendar_ytd', []);
    }

    #[Test]
    public function it_accepts_valid_bar_configuration(): void
    {
        $this->validator->validate(FormulaWidget::DISPLAY_BAR, null, [
            'series' => [
                ['code' => 'total_income', 'label' => 'Entrate'],
                ['code' => 'total_expenses', 'label' => 'Uscite'],
            ],
        ]);

        $this->assertTrue(true);
    }
}
