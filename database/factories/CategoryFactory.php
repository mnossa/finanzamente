<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['income', 'expense']);
        
        $expenseCategories = [
            ['name' => 'Alimentari', 'icon' => '🛒', 'color' => '#10b981'],
            ['name' => 'Spese Mediche', 'icon' => '🏥', 'color' => '#ef4444'],
            ['name' => 'Trasporti', 'icon' => '🚗', 'color' => '#3b82f6'],
            ['name' => 'Casa', 'icon' => '🏠', 'color' => '#f59e0b'],
            ['name' => 'Intrattenimento', 'icon' => '🎬', 'color' => '#8b5cf6'],
            ['name' => 'Abbigliamento', 'icon' => '👕', 'color' => '#ec4899'],
            ['name' => 'Istruzione', 'icon' => '📚', 'color' => '#06b6d4'],
        ];

        $incomeCategories = [
            ['name' => 'Stipendio', 'icon' => '💼', 'color' => '#10b981'],
            ['name' => 'Freelance', 'icon' => '💻', 'color' => '#3b82f6'],
            ['name' => 'Investimenti', 'icon' => '📈', 'color' => '#8b5cf6'],
            ['name' => 'Bonus', 'icon' => '🎁', 'color' => '#f59e0b'],
        ];

        $categoryData = $type === 'expense' 
            ? $this->faker->randomElement($expenseCategories)
            : $this->faker->randomElement($incomeCategories);

        return [
            'household_id' => Household::factory(),
            'name' => $categoryData['name'],
            'type' => $type,
            'icon' => $categoryData['icon'],
            'color' => $categoryData['color'],
            'is_fixed_expense' => false,
        ];
    }

    /**
     * Indicate that the category is an expense.
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
        ]);
    }

    /**
     * Indicate that the category is an income.
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'income',
        ]);
    }

    /**
     * Indicate that the category represents a fixed expense.
     */
    public function fixedExpense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
            'is_fixed_expense' => true,
        ]);
    }

    /**
     * Create category with custom name.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }
}
