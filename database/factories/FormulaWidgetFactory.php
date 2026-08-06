<?php

namespace Database\Factories;

use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormulaWidget>
 */
class FormulaWidgetFactory extends Factory
{
    protected $model = FormulaWidget::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'financial_variable_id' => FinancialVariable::factory(),
            'name' => fake()->sentence(3),
            'display_type' => FormulaWidget::DISPLAY_KPI,
            'period_preset' => 'rolling_30',
            'chart_config' => ['show_delta' => true, 'format' => 'currency'],
            'default_size' => 'md',
            'share_token' => null,
            'is_public' => false,
            'downloads_count' => 0,
            'source_id' => null,
            'is_official_template' => false,
            'template_slug' => null,
        ];
    }

    public function officialTemplate(string $slug): static
    {
        return $this->state(fn () => [
            'is_official_template' => true,
            'is_public' => true,
            'template_slug' => $slug,
        ]);
    }
}
