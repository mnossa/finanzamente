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
    | TELEGRAM_WEBHOOK_SECRET: secret token inviato nell'header
    | X-Telegram-Bot-Api-Secret-Token per verificare la sorgente webhook
    |
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
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
    | Unsplash — Ricerca immagini per articoli Magazine
    |--------------------------------------------------------------------------
    |
    | Access Key per l'API Unsplash, usata per cercare immagini libere da
    | copyright per le copertine degli articoli del magazine.
    | Registrazione: https://unsplash.com/developers
    | Istruzioni setup: tasks/unsplash-setup.md
    |
    */

    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY'),
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
        'api_key' => env('GOOGLE_DRIVE_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Umami Cloud — analytics (script lato React)
    |--------------------------------------------------------------------------
    |
    | UMAMI_ID: website id dalla dashboard Umami (stesso valore usato nei layout Blade).
    | Passato via Inertia così la produzione non dipende da VITE_UMAMI_ID nel build.
    |
    */
    'umami' => [
        'website_id' => env('UMAMI_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mollie — Sistema di pagamento
    |--------------------------------------------------------------------------
    |
    | API key per Mollie, usata per l'elaborazione dei pagamenti e la gestione
    | degli abbonamenti ricorrenti (piano Pro).
    | Registrazione: https://www.mollie.com/it
    |
    | MOLLIE_KEY: API key Mollie (test_... in dev, live_... in produzione)
    | MOLLIE_WEBHOOK_SECRET: segreto opzionale per validare i webhook
    |
    */

    'mollie' => [
        'key' => env('MOLLIE_KEY'),
        'webhook_secret' => env('MOLLIE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Brevo (ex Sendinblue) — Email Marketing & Waitlist
    |--------------------------------------------------------------------------
    |
    | API key per Brevo, usata per la gestione della waitlist Pro con double opt-in.
    | Registrazione: https://www.brevo.com/
    |
    | BREVO_API_KEY: API key ottenuta dal pannello Brevo
    | BREVO_WAITLIST_LIST_ID: ID della lista Brevo per la waitlist Pro
    | BREVO_DOUBLE_OPTIN_TEMPLATE_ID: ID del template email per il double opt-in
    | BREVO_DOUBLE_OPTIN_REDIRECT_URL: URL di redirect dopo conferma double opt-in
    | BREVO_ENABLED: false nel container app_e2e (test Playwright) per evitare chiamate API
    |
    */
    'brevo' => [
        'enabled' => filter_var(env('BREVO_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'api_key' => filter_var(env('BREVO_ENABLED', true), FILTER_VALIDATE_BOOLEAN) ? env('BREVO_API_KEY') : null,
        'waitlist_list_id' => (int) env('BREVO_WAITLIST_LIST_ID', 0),
        'marketing_list_id' => (int) env('BREVO_MARKETING_LIST_ID', 0),
        'double_optin_template_id' => (int) env('BREVO_DOUBLE_OPTIN_TEMPLATE_ID', 0),
        'double_optin_redirect_url' => env('BREVO_DOUBLE_OPTIN_REDIRECT_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tally.so — Webhook
    |--------------------------------------------------------------------------
    | TALLY_WEBHOOK_SECRET: copialo da Tally → Integrations → Webhooks → Signing secret.
    | Se vuoto, il webhook è disabilitato (ritorna 501).
    */
    'tally' => [
        'webhook_secret' => env('TALLY_WEBHOOK_SECRET', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Frankfurter — Tassi di cambio (FX)
    |--------------------------------------------------------------------------
    |
    | https://frankfurter.dev — API gratuita basata sui dati BCE, no API key.
    | Restituisce automaticamente il tasso del giorno feriale precedente quando
    | si chiede un weekend o festivo. URL configurabile per facilitare i test
    | (Http::fake) ed eventuale mirror self-hosted.
    |
    */
    'frankfurter' => [
        'base_url' => env('FRANKFURTER_BASE_URL', 'https://api.frankfurter.dev/v1'),
    ],

    /*
    | Servizio FastAPI ausiliario (cartella python-services). Variabile: PYTHON_SERVICES_URL.
    | Fallback PYTHON_LINKER_URL: deprecato, rimuovere dopo migrazione .env.
    */
    'python_services' => [
        'url' => env(
            'PYTHON_SERVICES_URL',
            env('PYTHON_LINKER_URL', 'http://127.0.0.1:8000')
        ),
        // true: i comandi schedulati avviano uvicorn solo se /health non risponde
        'manage_process' => env('PYTHON_SERVICES_MANAGE_PROCESS', true),
        'startup_timeout' => env('PYTHON_SERVICES_STARTUP_TIMEOUT', 120),
        'shutdown_after_use' => env('PYTHON_SERVICES_SHUTDOWN_AFTER_USE', true),
    ],

];
