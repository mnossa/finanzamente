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
        ['name' => 'Stipendio', 'type' => 'income', 'color' => '#22c55e', 'icon' => '💼'],
        ['name' => 'Bonus', 'type' => 'income', 'color' => '#16a34a', 'icon' => '🎁'],
        ['name' => 'Freelance', 'type' => 'income', 'color' => '#15803d', 'icon' => '💻'],
        ['name' => 'Investimenti', 'type' => 'income', 'color' => '#14532d', 'icon' => '📈'],
        ['name' => 'Affitto Ricevuto', 'type' => 'income', 'color' => '#4ade80', 'icon' => '🏠'],
        ['name' => 'Rimborsi', 'type' => 'income', 'color' => '#86efac', 'icon' => '💸'],
        ['name' => 'Regali Ricevuti', 'type' => 'income', 'color' => '#bbf7d0', 'icon' => '🎀'],
        ['name' => 'Vendite', 'type' => 'income', 'color' => '#dcfce7', 'icon' => '🛒'],
        ['name' => 'Altro (Entrata)', 'type' => 'income', 'color' => '#f0fdf4', 'icon' => '➕'],

        // Uscite - Casa
        ['name' => 'Affitto', 'type' => 'expense', 'color' => '#ef4444', 'icon' => '🏠'],
        ['name' => 'Mutuo', 'type' => 'expense', 'color' => '#dc2626', 'icon' => '🏦'],
        ['name' => 'Bollette', 'type' => 'expense', 'color' => '#b91c1c', 'icon' => '💡'],
        ['name' => 'Condominio', 'type' => 'expense', 'color' => '#991b1b', 'icon' => '🏢'],
        ['name' => 'Manutenzione Casa', 'type' => 'expense', 'color' => '#7f1d1d', 'icon' => '🔧'],

        // Uscite - Quotidiano
        ['name' => 'Spesa Alimentare', 'type' => 'expense', 'color' => '#f97316', 'icon' => '🛒'],
        ['name' => 'Ristoranti', 'type' => 'expense', 'color' => '#ea580c', 'icon' => '🍽️'],
        ['name' => 'Bar/Caffè', 'type' => 'expense', 'color' => '#c2410c', 'icon' => '☕'],

        // Uscite - Trasporti
        ['name' => 'Carburante', 'type' => 'expense', 'color' => '#eab308', 'icon' => '⛽'],
        ['name' => 'Trasporto Pubblico', 'type' => 'expense', 'color' => '#ca8a04', 'icon' => '🚌'],
        ['name' => 'Auto (Manutenzione)', 'type' => 'expense', 'color' => '#a16207', 'icon' => '🚗'],
        ['name' => 'Parcheggio', 'type' => 'expense', 'color' => '#854d0e', 'icon' => '🅿️'],
        ['name' => 'Assicurazione Auto', 'type' => 'expense', 'color' => '#713f12', 'icon' => '📋'],

        // Uscite - Salute e Benessere
        ['name' => 'Salute/Medico', 'type' => 'expense', 'color' => '#06b6d4', 'icon' => '🏥'],
        ['name' => 'Farmacia', 'type' => 'expense', 'color' => '#0891b2', 'icon' => '💊'],
        ['name' => 'Palestra/Sport', 'type' => 'expense', 'color' => '#0e7490', 'icon' => '🏋️'],
        ['name' => 'Cura Personale', 'type' => 'expense', 'color' => '#155e75', 'icon' => '💇'],

        // Uscite - Tempo Libero
        ['name' => 'Svago', 'type' => 'expense', 'color' => '#8b5cf6', 'icon' => '🎮'],
        ['name' => 'Viaggi/Vacanze', 'type' => 'expense', 'color' => '#7c3aed', 'icon' => '✈️'],
        ['name' => 'Abbonamenti', 'type' => 'expense', 'color' => '#6d28d9', 'icon' => '📺'],
        ['name' => 'Hobby', 'type' => 'expense', 'color' => '#5b21b6', 'icon' => '🎨'],

        // Uscite - Shopping
        ['name' => 'Abbigliamento', 'type' => 'expense', 'color' => '#ec4899', 'icon' => '👕'],
        ['name' => 'Elettronica', 'type' => 'expense', 'color' => '#db2777', 'icon' => '📱'],
        ['name' => 'Casa/Arredamento', 'type' => 'expense', 'color' => '#be185d', 'icon' => '🛋️'],

        // Uscite - Famiglia e Istruzione
        ['name' => 'Istruzione', 'type' => 'expense', 'color' => '#3b82f6', 'icon' => '📚'],
        ['name' => 'Figli', 'type' => 'expense', 'color' => '#2563eb', 'icon' => '👶'],
        ['name' => 'Animali Domestici', 'type' => 'expense', 'color' => '#1d4ed8', 'icon' => '🐾'],
        ['name' => 'Regali', 'type' => 'expense', 'color' => '#1e40af', 'icon' => '🎁'],

        // Uscite - Finanze
        ['name' => 'Tasse', 'type' => 'expense', 'color' => '#64748b', 'icon' => '📊'],
        ['name' => 'Commissioni Bancarie', 'type' => 'expense', 'color' => '#475569', 'icon' => '🏛️'],
        ['name' => 'Interessi Passivi', 'type' => 'expense', 'color' => '#334155', 'icon' => '📉'],

        // Uscite - Altro
        ['name' => 'Altro (Uscita)', 'type' => 'expense', 'color' => '#94a3b8', 'icon' => '➖'],
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
