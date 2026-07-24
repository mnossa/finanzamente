<?php

namespace Tests\Feature;

use App\Models\FormulaWidget;
use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_official_templates_idempotently(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);
        $firstCount = FormulaWidget::query()->where('is_official_template', true)->count();

        $this->seed(FormulaWidgetTemplateSeeder::class);
        $secondCount = FormulaWidget::query()->where('is_official_template', true)->count();

        $expected = count(config('formula_widget_templates'));
        $this->assertSame($expected, $firstCount);
        $this->assertSame($firstCount, $secondCount);
    }
}
