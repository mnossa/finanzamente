<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Product analytics (first-party, privacy-first)
    |--------------------------------------------------------------------------
    |
    | Aggregati giornalieri senza PII. Nessun user_id, email, IP, importo
    | o testo libero viene memorizzato. Vedi docs/product-analytics.md.
    |
    */

    'enabled' => env('PRODUCT_ANALYTICS_ENABLED', true),

    'retention_policy_key' => 'product_analytics_daily',

    'max_events_per_request' => 20,

    'max_dimension_keys' => 8,

    'blocked_dimension_keys' => [
        'email',
        'name',
        'nome',
        'cognome',
        'user_id',
        'userid',
        'household_id',
        'ip',
        'ip_address',
        'amount',
        'importo',
        'description',
        'descrizione',
        'note',
        'notes',
        'token',
        'password',
        'phone',
        'telefono',
        'iban',
        'account_id',
        'transaction_id',
    ],

    /*
    | Map event name prefixes / exact names → event_kind.
    | Default kind is "used".
    */
    'friction_events' => [
        'form.abandoned',
        'form.error',
    ],

    'friction_prefixes' => [
        'feature.friction',
    ],

    'error_events' => [
        'feature.error',
    ],

    'error_prefixes' => [
        'error.',
        'exception.',
    ],

    'performance_events' => [
        'feature.performance',
        'route.slow',
    ],

    /*
    | Slow-request sampling (server-side, route name only).
    */
    'slow_request_ms' => (int) env('PRODUCT_ANALYTICS_SLOW_MS', 1500),

    'slow_request_enabled' => env('PRODUCT_ANALYTICS_SLOW_ENABLED', true),
];
