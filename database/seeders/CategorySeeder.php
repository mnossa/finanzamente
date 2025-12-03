<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Services\CategoryService;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * Run the database seeds.
     *
     * Crea le categorie predefinite per tutte le household esistenti.
     * Nota: Le nuove household riceveranno automaticamente le categorie
     * tramite l'HouseholdObserver.
     */
    public function run(): void
    {
        $households = Household::all();

        foreach ($households as $household) {
            $this->categoryService->createDefaultCategoriesForHousehold($household);
        }

        $this->command->info('Categorie predefinite create per ' . $households->count() . ' household.');
    }
}
