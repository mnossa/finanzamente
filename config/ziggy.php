<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes excluded from the Ziggy payload
    |--------------------------------------------------------------------------
    |
    | Reduces inline @routes JSON on authenticated Inertia pages. Marketing
    | landings and server-only endpoints are not called via route() in JS.
    |
    */
    'except' => [
        'telegram.webhook',
        'robots',
        'sitemap',
        'sanctum.*',
        'horizon.*',
        'telescope.*',
        'debugbar.*',
    ],

];
