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
    | Google Sheets — export household (migrazione / backup)
    |--------------------------------------------------------------------------
    |
    | Service account JSON da Google Cloud Console (Sheets API + Drive API).
    | Condividi lo spreadsheet creato con GOOGLE_SHEETS_SHARE_WITH.
    |
    */
    'google_sheets' => [
        'credentials_path' => env(
            'GOOGLE_SHEETS_CREDENTIALS_PATH',
            storage_path('app/google-service-account.json')
        ),
        'share_with' => env('GOOGLE_SHEETS_SHARE_WITH', ''),
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
