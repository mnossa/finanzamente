<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'name' => $this->faker->randomElement(['Conto Principale', 'Carta Credito', 'Portafoglio', 'Risparmio']),
            'type' => $this->faker->randomElement(['bank', 'cash', 'card', 'broker', 'crypto', 'other']),
            'initial_balance' => $this->faker->randomFloat(2, 0, 10000),
            'current_balance' => $this->faker->randomFloat(2, 0, 10000),
            'currency_code' => 'EUR',
            'active' => true,
            'is_private' => false,
            'owner_user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the account is a bank account.
     */
    public function bank(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bank',
            'name' => $this->faker->randomElement(['Conto Corrente', 'Conto Principale', 'Conto Banca']),
        ]);
    }

    /**
     * Indicate that the account is cash.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cash',
            'name' => $this->faker->randomElement(['Contanti', 'Portafoglio', 'Cash']),
        ]);
    }

    /**
     * Indicate that the account is a credit card.
     */
    public function card(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'card',
            'name' => $this->faker->randomElement(['Carta di Credito', 'Visa', 'Mastercard']),
        ]);
    }

    /**
     * Indicate that the account is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    /**
     * Indicate that the account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Create account with zero balance.
     */
    public function zeroBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'initial_balance' => 0.00,
            'current_balance' => 0.00,
        ]);
    }

    /**
     * Create account with specific balance.
     */
    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'initial_balance' => $balance,
            'current_balance' => $balance,
        ]);
    }
}
