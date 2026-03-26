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
                'Tracciamento entrate e uscite',
                'Fino a 3 conti bancari',
                'Categorie e tag',
                'Budget mensile',
                'Report base',
                'App mobile responsive',
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
                'Conti illimitati',
                'Investimenti e asset allocation',
                'Obiettivi finanziari avanzati',
                'Import da file bancari',
                'Integrazione Telegram',
                'Analisi trend e proiezioni',
                'Supporto prioritario',
            ],
            'mollie_plan_id_monthly' => env('MOLLIE_PRO_PLAN_ID_MONTHLY'),
            'mollie_plan_id_annual' => env('MOLLIE_PRO_PLAN_ID_ANNUAL'),
        ],
    ],
];
