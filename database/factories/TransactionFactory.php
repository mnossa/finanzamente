<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'account_id'  => Account::factory(),
            'category_id' => Category::factory(),
            'amount'      => $this->faker->randomFloat(2, -1000, -1),
            'currency_code' => 'EUR',
            'date'        => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'description' => $this->faker->optional()->sentence(),
            'recurring'   => false,
            'is_private'  => false,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $this->faker->randomFloat(2, -1000, -1),
        ]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $this->faker->randomFloat(2, 1, 5000),
        ]);
    }
}
