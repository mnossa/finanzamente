<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Household::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true).' Family',
            'owner_user_id' => User::factory(),
            'financial_management_type' => $this->faker->randomElement([
                Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
                Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
            ]),
            'balance_percentages' => null, // Le percentuali saranno impostate dopo l'aggiunta dei membri
        ];
    }

    /**
     * Indicate that the household uses shared wallet mode.
     */
    public function sharedWallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);
    }

    /**
     * Indicate that the household uses debt balancing mode.
     */
    public function debtBalancing(): static
    {
        return $this->state(fn (array $attributes) => [
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
        ]);
    }

    /**
     * Create household with custom balance percentages.
     *
     * @param  array  $percentages  Array di percentuali [user_id => percentage]
     */
    public function withBalancePercentages(array $percentages): static
    {
        return $this->state(fn (array $attributes) => [
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
            'balance_percentages' => $percentages,
        ]);
    }

    /**
     * Create household with 50/50 split for two users.
     */
    public function fiftyFiftySplit(int $user1Id, int $user2Id): static
    {
        return $this->withBalancePercentages([
            $user1Id => 50.0,
            $user2Id => 50.0,
        ]);
    }

    /**
     * Create household with 70/30 split for two users.
     */
    public function seventyThirtySplit(int $user1Id, int $user2Id): static
    {
        return $this->withBalancePercentages([
            $user1Id => 70.0,
            $user2Id => 30.0,
        ]);
    }
}
