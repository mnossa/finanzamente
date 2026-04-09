<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature flag: abilita/disabilita l'acquisto del pacchetto Pro
    |--------------------------------------------------------------------------
    */
    'pro_enabled' => env('PRO_PLAN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Sconto percentuale per il piano annuale Pro
    |--------------------------------------------------------------------------
    */
    'annual_discount_percent' => (int) env('PRO_ANNUAL_DISCOUNT_PERCENT', 20),

    /*
    |--------------------------------------------------------------------------
    | Limiti del piano Base
    |--------------------------------------------------------------------------
    */
    'base_limits' => [
        'max_accounts'               => 5,
        'max_households'             => 1,
        'max_recurring_transactions' => 5,
        'max_refunds'                => 10,
        'max_debts_credits'          => 5,
        'max_financial_goals'        => 1,
        'can_invite_members'         => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Definizione piani disponibili
    | Aggiungere qui nuovi piani futuri senza modificare il codice applicativo
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'base' => [
            'name' => 'Base',
            'label' => 'Gratis per sempre',
            'price_monthly_cents' => 0,
            'currency' => 'EUR',
            'features' => [
                // --- prime 6 visibili subito ---
                'Dashboard e panoramica finanziaria',
                'Transazioni illimitate',
                'Budget mensile',
                'Import bancario da CSV/XLS',
                'Categorie personalizzate',
                'Fino a 5 conti bancari',
                // --- espandibili ---
                'Tag per le transazioni',
                'Trasferimenti tra conti',
                'Fino a 5 transazioni ricorrenti',
                'Fino a 10 rimborsi attivi',
                'Fino a 5 debiti/crediti attivi',
                '1 obiettivo finanziario',
                '1 sola household',
            ],
            'mollie_plan_id_monthly' => null,
            'mollie_plan_id_annual' => null,
        ],
        'pro' => [
            'name' => 'Pro',
            'label' => 'Tutto, senza limiti',
            'price_monthly_cents' => (int) env('PRO_PRICE_MONTHLY_CENTS', 299), // 2,99 €
            'currency' => 'EUR',
            'features' => [
                // --- prime 6 visibili subito ---
                'Tutto del piano Base',
                'Investimenti e portafoglio',
                'Tracker spese detraibili (mediche, mutuo…)',
                'Household illimitate con membri',
                'Simulazioni finanziarie',
                'Lifestyle Inflation Score',
                // --- espandibili ---
                'Conti bancari illimitati',
                'Transazioni ricorrenti illimitate',
                'Rimborsi illimitati',
                'Debiti e crediti illimitati',
                'Obiettivi finanziari illimitati',
                'Asset allocation',
                'Analisi investimenti',
                'Integrazione Telegram',
                'Gestione IVA (Partita IVA)',
                'Trasferimenti tra household',
                'Export PDF e XLS avanzati (prossimamente)',
            ],
            'mollie_plan_id_monthly' => env('MOLLIE_PRO_PLAN_ID_MONTHLY'),
            'mollie_plan_id_annual' => env('MOLLIE_PRO_PLAN_ID_ANNUAL'),
        ],
    ],
];
