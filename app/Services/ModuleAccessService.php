<?php

namespace App\Services;

use App\Models\User;

/**
 * Service per gestire l'accesso ai moduli dell'applicazione.
 * 
 * Determina quali moduli sono disponibili per un utente in base a:
 * - Impostazioni del profilo (profile_settings)
 * - Piano/pacchetto sottoscritto (free/pro, implementazione futura)
 * 
 * I moduli sono organizzati in categorie:
 * - base: moduli sempre disponibili
 * - planning: pianificazione finanziaria
 * - fiscal: gestione fiscale (richiede has_vat)
 * - investments: investimenti (richiede tracks_investments)
 * - special: transazioni speciali
 */
class ModuleAccessService
{
    /**
     * Definizione di tutti i moduli disponibili nell'applicazione.
     * Ogni modulo ha:
     * - id: identificativo unico
     * - name: nome visualizzato
     * - category: categoria di appartenenza
     * - routes: array di pattern di rotte associate
     * - requires: array di requisiti per l'accesso
     */
    private const MODULES = [
        // Moduli Base (sempre disponibili)
        'dashboard' => [
            'id' => 'dashboard',
            'name' => 'Dashboard',
            'category' => 'base',
            'routes' => ['dashboard'],
            'requires' => [],
        ],
        'accounts' => [
            'id' => 'accounts',
            'name' => 'Conti',
            'category' => 'base',
            'routes' => ['accounts.*'],
            'requires' => [],
        ],
        'transactions' => [
            'id' => 'transactions',
            'name' => 'Transazioni',
            'category' => 'base',
            'routes' => ['transactions.*'],
            'requires' => [],
        ],
        'categories' => [
            'id' => 'categories',
            'name' => 'Categorie',
            'category' => 'base',
            'routes' => ['categories.*'],
            'requires' => [],
        ],
        'transfers' => [
            'id' => 'transfers',
            'name' => 'Trasferimenti',
            'category' => 'base',
            'routes' => ['transfers.*'],
            'requires' => [],
        ],
        
        // Transazioni Speciali
        'inter_household_transfers' => [
            'id' => 'inter_household_transfers',
            'name' => 'Trasferimenti tra Household',
            'category' => 'special',
            'routes' => ['inter-household-transfers.*'],
            'requires' => [],
        ],
        'refunds' => [
            'id' => 'refunds',
            'name' => 'Rimborsi',
            'category' => 'special',
            'routes' => ['refunds.*'],
            'requires' => [],
        ],
        'recurring_transactions' => [
            'id' => 'recurring_transactions',
            'name' => 'Transazioni Ricorrenti',
            'category' => 'special',
            'routes' => ['recurring-transactions.*'],
            'requires' => [],
        ],
        
        // Pianificazione
        'budgets' => [
            'id' => 'budgets',
            'name' => 'Budget',
            'category' => 'planning',
            'routes' => ['budgets.*'],
            'requires' => [],
        ],
        'debts_credits' => [
            'id' => 'debts_credits',
            'name' => 'Debiti e Crediti',
            'category' => 'planning',
            'routes' => ['debts-credits.*'],
            'requires' => [],
        ],
        'financial_goals' => [
            'id' => 'financial_goals',
            'name' => 'Obiettivi Finanziari',
            'category' => 'planning',
            'routes' => ['financial-goals.*'],
            'requires' => [],
        ],
        
        // Fiscale
        'tax_refund_730' => [
            'id' => 'tax_refund_730',
            'name' => 'Rimborso 730',
            'category' => 'fiscal',
            'routes' => ['tax-deductions.*'], // Temporaneo, poi separare le rotte
            'requires' => [],
        ],
        'vat_management' => [
            'id' => 'vat_management',
            'name' => 'Gestione IVA',
            'category' => 'fiscal',
            'routes' => ['vat-management.*'], // Implementazione futura
            'requires' => ['has_vat'],
        ],
        
        // Investimenti (richiede tracks_investments)
        'investments' => [
            'id' => 'investments',
            'name' => 'Investimenti',
            'category' => 'investments',
            'routes' => ['investments.*'],
            'requires' => ['tracks_investments'],
        ],
        'investment_assets' => [
            'id' => 'investment_assets',
            'name' => 'Asset Investimenti',
            'category' => 'investments',
            'routes' => ['investment-assets.*'],
            'requires' => ['tracks_investments'],
        ],
    ];

    /**
     * Ottiene tutti i moduli disponibili per un utente.
     * 
     * @param User $user
     * @return array Array associativo [moduleId => moduleData]
     */
    public function getAvailableModules(User $user): array
    {
        $profileSettings = $user->profile_settings ?? [];
        $availableModules = [];

        foreach (self::MODULES as $moduleId => $module) {
            if ($this->canAccessModule($user, $module, $profileSettings)) {
                $availableModules[$moduleId] = [
                    ...$module,
                    'enabled' => true,
                ];
            }
        }

        return $availableModules;
    }

    /**
     * Ottiene tutti i moduli (disponibili e non) con info su accessibilità.
     * Utile per mostrare moduli bloccati con suggerimenti per sbloccarli.
     * 
     * @param User $user
     * @return array
     */
    public function getAllModulesWithAccess(User $user): array
    {
        $profileSettings = $user->profile_settings ?? [];
        $modules = [];

        foreach (self::MODULES as $moduleId => $module) {
            $canAccess = $this->canAccessModule($user, $module, $profileSettings);
            $missingRequirements = $this->getMissingRequirements($module, $profileSettings);

            // Non mostrare moduli con requisiti non configurabili (come has_vat per utenti persona)
            if (in_array('has_vat', $module['requires']) && $user->user_type !== 'partita_iva') {
                continue; // Salta questo modulo, non è rilevante per l'utente
            }

            $modules[$moduleId] = [
                ...$module,
                'enabled' => $canAccess,
                'locked' => !$canAccess,
                'missing_requirements' => $missingRequirements,
                'unlock_hint' => $this->getUnlockHint($missingRequirements),
            ];
        }

        return $modules;
    }

    /**
     * Verifica se un utente può accedere a un modulo specifico.
     * 
     * @param User $user
     * @param string $moduleId
     * @return bool
     */
    public function canAccessModuleById(User $user, string $moduleId): bool
    {
        $module = self::MODULES[$moduleId] ?? null;
        
        if (!$module) {
            return false;
        }

        $profileSettings = $user->profile_settings ?? [];
        return $this->canAccessModule($user, $module, $profileSettings);
    }

    /**
     * Verifica se un utente può accedere a una rotta specifica.
     * 
     * @param User $user
     * @param string $routeName
     * @return bool
     */
    public function canAccessRoute(User $user, string $routeName): bool
    {
        foreach (self::MODULES as $module) {
            foreach ($module['routes'] as $routePattern) {
                if ($this->matchesRoute($routeName, $routePattern)) {
                    return $this->canAccessModuleById($user, $module['id']);
                }
            }
        }

        // Se la rotta non è associata a nessun modulo, permetti l'accesso
        return true;
    }

    /**
     * Ottiene i moduli raggruppati per categoria, con informazioni di accesso.
     * 
     * @param User $user
     * @return array
     */
    public function getModulesByCategory(User $user): array
    {
        $modules = $this->getAllModulesWithAccess($user);
        $grouped = [];

        foreach ($modules as $module) {
            $category = $module['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [
                    'name' => $this->getCategoryName($category),
                    'modules' => [],
                ];
            }
            $grouped[$category]['modules'][] = $module;
        }

        return $grouped;
    }

    /**
     * Verifica se l'utente può accedere a un modulo.
     */
    private function canAccessModule(User $user, array $module, array $profileSettings): bool
    {
        // Verifica requisiti dal profilo
        foreach ($module['requires'] as $requirement) {
            // Gestione speciale per has_vat: controlla user_type invece di profile_settings
            if ($requirement === 'has_vat') {
                if ($user->user_type !== 'partita_iva') {
                    return false;
                }
                continue;
            }
            
            if (!($profileSettings[$requirement] ?? false)) {
                return false;
            }
        }

        // TODO: Aggiungere qui la verifica del piano/pacchetto (free/pro)
        // if (!$this->hasPlanAccess($user, $module)) {
        //     return false;
        // }

        return true;
    }

    /**
     * Ottiene i requisiti mancanti per un modulo.
     */
    private function getMissingRequirements(array $module, array $profileSettings): array
    {
        // Non restituire mai 'has_vat' come requisito mancante
        // perché non è attivabile dalle impostazioni del profilo
        $missing = [];

        foreach ($module['requires'] as $requirement) {
            if ($requirement === 'has_vat') {
                continue; // Skip, non è configurabile dall'utente
            }
            
            if (!($profileSettings[$requirement] ?? false)) {
                $missing[] = $requirement;
            }
        }

        return $missing;
    }

    /**
     * Genera un suggerimento per sbloccare un modulo.
     */
    private function getUnlockHint(array $missingRequirements): ?string
    {
        if (empty($missingRequirements)) {
            return null;
        }

        $hints = [
            'tracks_investments' => 'Attiva "Gestione Investimenti" nelle impostazioni del profilo per sbloccare questo modulo.',
        ];

        $requirement = $missingRequirements[0];
        return $hints[$requirement] ?? null;
    }

    /**
     * Verifica se una rotta corrisponde a un pattern.
     */
    private function matchesRoute(string $routeName, string $pattern): bool
    {
        // Converti il pattern in regex
        $regex = str_replace(['*', '.'], ['.*', '\.'], $pattern);
        return (bool) preg_match('/^' . $regex . '$/', $routeName);
    }

    /**
     * Ottiene il nome leggibile di una categoria.
     */
    private function getCategoryName(string $category): string
    {
        $names = [
            'base' => 'Gestione Base',
            'special' => 'Transazioni Speciali',
            'planning' => 'Pianificazione',
            'fiscal' => 'Fiscale',
            'investments' => 'Investimenti',
        ];

        return $names[$category] ?? ucfirst($category);
    }
}
