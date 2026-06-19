<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Routes excluded from the Ziggy payload
    |--------------------------------------------------------------------------
    |
    | Reduces inline @routes JSON on authenticated Inertia pages. Public magazine,
    | marketing landings and server-only endpoints are not called via route() in JS.
    |
    */
    'except' => [
        'magazine.*',
        'landing.*',
        'waitlist.*',
        'mollie.webhook',
        'telegram.webhook',
        'robots',
        'sitemap',
        'sanctum.*',
        'horizon.*',
        'telescope.*',
        'debugbar.*',
    ],

];
