<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed currencies first (required for accounts)
        $this->call(CurrencySeeder::class);

        // Seed categories (required for transactions)
        $this->call(CategorySeeder::class);

        // Seed magazine categories
        $this->call(MagazineCategorySeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'profile_completed' => true, // Utente esistente, già configurato
            'profile_settings' => [
                'has_vat' => false,
                'family_status' => 'single',
                'tracks_investments' => false,
                'completed_at' => now()->toISOString(),
            ],
        ]);
    }
}
