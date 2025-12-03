<?php

namespace App\Observers;

use App\Models\Household;
use App\Services\CategoryService;

/**
 * HouseholdObserver
 *
 * Observer per il modello Household.
 * Gestisce la creazione automatica delle categorie predefinite
 * quando viene creata una nuova household.
 */
class HouseholdObserver
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * Handle the Household "created" event.
     *
     * Crea automaticamente le categorie predefinite per la nuova household.
     */
    public function created(Household $household): void
    {
        $this->categoryService->createDefaultCategoriesForHousehold($household);
    }
}
