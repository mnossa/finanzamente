<?php

namespace Database\Factories;

use App\Models\BankImportLayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankImportLayoutFactory extends Factory
{
    protected $model = BankImportLayout::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'household_id' => null,
            'name' => $this->faker->words(3, true),
            'bank_name' => $this->faker->randomElement(array_keys(BankImportLayout::BANK_NAMES)),
            'column_mapping' => [
                'date' => 0,
                'description' => 1,
                'amount' => 2,
                'notes' => null,
            ],
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
        ];
    }
}
