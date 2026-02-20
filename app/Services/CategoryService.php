<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Household;

/**
 * CategoryService
 *
 * Servizio per la gestione delle categorie.
 * Include la creazione delle categorie predefinite per le household.
 */
class CategoryService
{
    /**
     * Categorie predefinite per ogni household.
     */
    private array $defaultCategories = [
        // Entrate
        ['name' => 'Stipendio', 'type' => 'income', 'color' => '#22c55e', 'icon' => '💼', 'is_fixed_expense' => false],
        ['name' => 'Bonus', 'type' => 'income', 'color' => '#16a34a', 'icon' => '🎁', 'is_fixed_expense' => false],
        ['name' => 'Freelance', 'type' => 'income', 'color' => '#15803d', 'icon' => '💻', 'is_fixed_expense' => false],
        ['name' => 'Investimenti', 'type' => 'income', 'color' => '#14532d', 'icon' => '📈', 'is_fixed_expense' => false],
        ['name' => 'Affitto Ricevuto', 'type' => 'income', 'color' => '#4ade80', 'icon' => '🏠', 'is_fixed_expense' => false],
        ['name' => 'Rimborsi', 'type' => 'income', 'color' => '#86efac', 'icon' => '💸', 'is_fixed_expense' => false],
        ['name' => 'Regali Ricevuti', 'type' => 'income', 'color' => '#bbf7d0', 'icon' => '🎀', 'is_fixed_expense' => false],
        ['name' => 'Vendite', 'type' => 'income', 'color' => '#dcfce7', 'icon' => '🛒', 'is_fixed_expense' => false],
        ['name' => 'Altro (Entrata)', 'type' => 'income', 'color' => '#f0fdf4', 'icon' => '➕', 'is_fixed_expense' => false],

        // Uscite - Casa (spese fisse ricorrenti)
        ['name' => 'Affitto', 'type' => 'expense', 'color' => '#ef4444', 'icon' => '🏠', 'is_fixed_expense' => true],
        ['name' => 'Mutuo', 'type' => 'expense', 'color' => '#dc2626', 'icon' => '🏦', 'is_fixed_expense' => true],
        ['name' => 'Bollette', 'type' => 'expense', 'color' => '#b91c1c', 'icon' => '💡', 'is_fixed_expense' => true],
        ['name' => 'Condominio', 'type' => 'expense', 'color' => '#991b1b', 'icon' => '🏢', 'is_fixed_expense' => true],
        ['name' => 'Manutenzione Casa', 'type' => 'expense', 'color' => '#7f1d1d', 'icon' => '🔧', 'is_fixed_expense' => false],

        // Uscite - Quotidiano (spese variabili)
        ['name' => 'Spesa Alimentare', 'type' => 'expense', 'color' => '#f97316', 'icon' => '🛒', 'is_fixed_expense' => false],
        ['name' => 'Ristoranti', 'type' => 'expense', 'color' => '#ea580c', 'icon' => '🍽️', 'is_fixed_expense' => false],
        ['name' => 'Bar/Caffè', 'type' => 'expense', 'color' => '#c2410c', 'icon' => '☕', 'is_fixed_expense' => false],

        // Uscite - Trasporti (miste)
        ['name' => 'Carburante', 'type' => 'expense', 'color' => '#eab308', 'icon' => '⛽', 'is_fixed_expense' => false],
        ['name' => 'Trasporto Pubblico', 'type' => 'expense', 'color' => '#ca8a04', 'icon' => '🚌', 'is_fixed_expense' => true],
        ['name' => 'Auto (Manutenzione)', 'type' => 'expense', 'color' => '#a16207', 'icon' => '🚗', 'is_fixed_expense' => false],
        ['name' => 'Parcheggio', 'type' => 'expense', 'color' => '#854d0e', 'icon' => '🅿️', 'is_fixed_expense' => false],
        ['name' => 'Assicurazione Auto', 'type' => 'expense', 'color' => '#713f12', 'icon' => '📋', 'is_fixed_expense' => true],

        // Uscite - Salute e Benessere (miste)
        ['name' => 'Salute/Medico', 'type' => 'expense', 'color' => '#06b6d4', 'icon' => '🏥', 'is_fixed_expense' => false],
        ['name' => 'Farmacia', 'type' => 'expense', 'color' => '#0891b2', 'icon' => '💊', 'is_fixed_expense' => false],
        ['name' => 'Palestra/Sport', 'type' => 'expense', 'color' => '#0e7490', 'icon' => '🏋️', 'is_fixed_expense' => true],
        ['name' => 'Cura Personale', 'type' => 'expense', 'color' => '#155e75', 'icon' => '💇', 'is_fixed_expense' => false],

        // Uscite - Tempo Libero (miste)
        ['name' => 'Svago', 'type' => 'expense', 'color' => '#8b5cf6', 'icon' => '🎮', 'is_fixed_expense' => false],
        ['name' => 'Viaggi/Vacanze', 'type' => 'expense', 'color' => '#7c3aed', 'icon' => '✈️', 'is_fixed_expense' => false],
        ['name' => 'Abbonamenti', 'type' => 'expense', 'color' => '#6d28d9', 'icon' => '📺', 'is_fixed_expense' => true],
        ['name' => 'Hobby', 'type' => 'expense', 'color' => '#5b21b6', 'icon' => '🎨', 'is_fixed_expense' => false],

        // Uscite - Shopping (spese variabili)
        ['name' => 'Abbigliamento', 'type' => 'expense', 'color' => '#ec4899', 'icon' => '👕', 'is_fixed_expense' => false],
        ['name' => 'Elettronica', 'type' => 'expense', 'color' => '#db2777', 'icon' => '📱', 'is_fixed_expense' => false],
        ['name' => 'Casa/Arredamento', 'type' => 'expense', 'color' => '#be185d', 'icon' => '🛋️', 'is_fixed_expense' => false],

        // Uscite - Famiglia e Istruzione (miste)
        ['name' => 'Istruzione', 'type' => 'expense', 'color' => '#3b82f6', 'icon' => '📚', 'is_fixed_expense' => true],
        ['name' => 'Figli', 'type' => 'expense', 'color' => '#2563eb', 'icon' => '👶', 'is_fixed_expense' => false],
        ['name' => 'Animali Domestici', 'type' => 'expense', 'color' => '#1d4ed8', 'icon' => '🐾', 'is_fixed_expense' => false],
        ['name' => 'Regali', 'type' => 'expense', 'color' => '#1e40af', 'icon' => '🎁', 'is_fixed_expense' => false],

        // Uscite - Finanze (spese fisse)
        ['name' => 'Tasse', 'type' => 'expense', 'color' => '#64748b', 'icon' => '📊', 'is_fixed_expense' => true],
        ['name' => 'Commissioni Bancarie', 'type' => 'expense', 'color' => '#475569', 'icon' => '🏛️', 'is_fixed_expense' => true],
        ['name' => 'Interessi Passivi', 'type' => 'expense', 'color' => '#334155', 'icon' => '📉', 'is_fixed_expense' => false],

        // Uscite - Altro (spesa variabile)
        ['name' => 'Altro (Uscita)', 'type' => 'expense', 'color' => '#94a3b8', 'icon' => '➖', 'is_fixed_expense' => false],
    ];

    /**
     * Crea le categorie predefinite per una household.
     *
     * @param Household $household La household per cui creare le categorie
     * @return int Numero di categorie create
     */
    public function createDefaultCategoriesForHousehold(Household $household): int
    {
        $created = 0;

        foreach ($this->defaultCategories as $categoryData) {
            Category::firstOrCreate(
                [
                    'household_id' => $household->id,
                    'name' => $categoryData['name'],
                ],
                $categoryData
            );
            $created++;
        }

        return $created;
    }

    /**
     * Restituisce l'elenco delle categorie predefinite.
     *
     * @return array
     */
    public function getDefaultCategories(): array
    {
        return $this->defaultCategories;
    }
}
