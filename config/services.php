<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Financial Data Services
    |--------------------------------------------------------------------------
    |
    | API keys for financial data providers used to fetch asset prices,
    | historical data, and ISIN/ticker mappings.
    |
    | Providers disponibili:
    | - yahoo_finance: Yahoo Finance via RapidAPI (consigliato, ~500 req/mese gratis)
    | - alpha_vantage: Alpha Vantage (25 req/giorno gratis - molto limitato)
    |
    */

    'asset_price' => [
        'provider' => env('ASSET_PRICE_PROVIDER', 'yahoo_finance'),
    ],

    'yahoo_finance' => [
        'key' => env('YAHOO_FINANCE_API_KEY'),
    ],

    'alpha_vantage' => [
        'key' => env('ALPHA_VANTAGE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot
    |--------------------------------------------------------------------------
    |
    | Credenziali per il bot Telegram usato per l'ingest di spese via chat.
    | TELEGRAM_BOT_TOKEN: token ottenuto da @BotFather
    | TELEGRAM_BOT_USERNAME: username del bot (senza @), per generare i link
    |
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mistral AI (La Plateforme)
    |--------------------------------------------------------------------------
    |
    | API key per Mistral AI, usata per l'estrazione OCR da scontrini
    | tramite il modello Pixtral (pixtral-12b-2409).
    | Registrazione: https://console.mistral.ai/
    |
    */

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Drive
    |--------------------------------------------------------------------------
    |
    | Credenziali per l'integrazione con Google Drive (importazione file).
    | Crea le credenziali su https://console.cloud.google.com/
    |
    */

    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID', ''),
        'api_key'   => env('GOOGLE_DRIVE_API_KEY', ''),
    ],

];
