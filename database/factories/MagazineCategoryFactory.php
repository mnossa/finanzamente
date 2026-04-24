<?php

namespace Database\Factories;

use App\Models\MagazineCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MagazineCategoryFactory extends Factory
{
    protected $model = MagazineCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();
        return [
            'slug'        => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'name'        => ucfirst($name),
            'description' => $this->faker->sentence(),
            'color'       => $this->faker->hexColor(),
            'sort_order'  => $this->faker->numberBetween(1, 100),
        ];
    }
}
