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
        'max_accounts'    => 3,
        'max_households'  => 1,
        'can_invite_members' => false,
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
                'Dashboard e panoramica finanziaria',
                'Fino a 3 conti bancari',
                'Transazioni illimitate',
                'Categorie personalizzate',
                'Tag per le transazioni',
                'Trasferimenti tra conti',
                'Budget mensile',
                'Import bancario da CSV/XLS',
                '1 sola household',
            ],
            'mollie_plan_id_monthly' => null,
            'mollie_plan_id_annual' => null,
        ],
        'pro' => [
            'name' => 'Pro',
            'label' => 'Tutte le funzionalità',
            'price_monthly_cents' => (int) env('PRO_PRICE_MONTHLY_CENTS', 990), // 9,90 €
            'currency' => 'EUR',
            'features' => [
                'Tutto del piano Base',
                'Conti bancari illimitati',
                'Household illimitate con membri',
                'Transazioni ricorrenti',
                'Rimborsi',
                'Debiti e crediti',
                'Obiettivi finanziari',
                'Investimenti e portafoglio',
                'Asset allocation',
                'Analisi investimenti',
                'Simulazioni finanziarie',
                'Integrazione Telegram',
                'Inbox Telegram (staging)',
                'Detrazioni fiscali e 730',
                'Gestione IVA (Partita IVA)',
                'Trasferimenti tra household',
                'Lifestyle Inflation Score',
                'Export PDF e XLS avanzati',
            ],
            'mollie_plan_id_monthly' => env('MOLLIE_PRO_PLAN_ID_MONTHLY'),
            'mollie_plan_id_annual' => env('MOLLIE_PRO_PLAN_ID_ANNUAL'),
        ],
    ],
];
