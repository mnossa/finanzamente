<?php

namespace Database\Factories;

use App\Models\FinancialVariable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialVariable>
 */
class FinancialVariableFactory extends Factory
{
    protected $model = FinancialVariable::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'user_id' => User::factory(),
            'code' => str_replace('-', '_', fake()->unique()->slug(2)),
            'name' => $name,
            'type' => FinancialVariable::TYPE_STATIC,
            'static_value' => fake()->randomFloat(2, 100, 5000),
            'formula_string' => null,
            'share_token' => null,
            'is_public' => false,
            'downloads_count' => 0,
            'source_id' => null,
            'is_official_template' => false,
            'template_slug' => null,
        ];
    }

    public function formula(string $formulaString): static
    {
        return $this->state(fn () => [
            'type' => FinancialVariable::TYPE_FORMULA,
            'static_value' => null,
            'formula_string' => $formulaString,
        ]);
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
