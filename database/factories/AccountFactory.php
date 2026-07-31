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
            'interest_rate' => null,
            'ticket_unit_value' => null,
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
            'interest_rate' => null,
            'name' => $this->faker->randomElement(['Conto Corrente', 'Conto Principale', 'Conto Banca']),
        ]);
    }

    public function savingsDeposit(float $interestRate = 2.5): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bank',
            'interest_rate' => $interestRate,
            'ticket_unit_value' => null,
            'name' => $this->faker->randomElement(['Conto Deposito', 'Deposito Vincolato']),
        ]);
    }

    public function mealVoucher(float $ticketUnitValue = 8.0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Account::MEAL_VOUCHER_TYPE,
            'interest_rate' => null,
            'ticket_unit_value' => $ticketUnitValue,
            'name' => $this->faker->randomElement(['Buoni pasto', 'Ticket restaurant']),
        ]);
    }

    public function pensionFund(?string $externalUrl = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Account::PENSION_FUND_TYPE,
            'interest_rate' => null,
            'ticket_unit_value' => null,
            'external_url' => $externalUrl,
            'name' => $this->faker->randomElement(['Fondo pensione', 'Previdenza complementare', 'PIP']),
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
