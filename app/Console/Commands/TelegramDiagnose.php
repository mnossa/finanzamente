<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Diagnostica configurazione bot Telegram (token, webhook, secret).
 */
class TelegramDiagnose extends Command
{
    protected $signature = 'telegram:diagnose';

    protected $description = 'Verifica token, webhook registrato e allineamento TELEGRAM_WEBHOOK_SECRET';

    public function handle(): int
    {
        $token = trim((string) config('services.telegram.bot_token', ''));
        $username = trim((string) config('services.telegram.bot_username', ''));
        $secret = trim((string) config('services.telegram.webhook_secret', ''));
        $appUrl = rtrim((string) config('app.url'), '/');
        $expectedWebhookUrl = $appUrl.'/telegram/webhook';

        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN mancante o vuoto (controlla .env e `php artisan config:clear`).');

            return self::FAILURE;
        }

        $this->info('Token bot: configurato ('.substr($token, 0, 8).'…)');
        $this->line('Username atteso: '.($username !== '' ? '@'.$username : '(non impostato)'));
        $this->line('APP_URL: '.$appUrl);
        $this->line('Webhook atteso: '.$expectedWebhookUrl);
        $this->line('TELEGRAM_WEBHOOK_SECRET: '.($secret !== '' ? 'impostato ('.strlen($secret).' caratteri)' : 'NON impostato (endpoint accetta richieste senza header secret)'));
        $this->newLine();

        $meResponse = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");
        if (! $meResponse->successful() || $meResponse->json('ok') !== true) {
            $this->error('getMe fallito: token non valido o API Telegram irraggiungibile.');
            $this->line($meResponse->body());

            return self::FAILURE;
        }

        $bot = $meResponse->json('result');
        $this->info('Bot attivo: @'.($bot['username'] ?? '?').' ('.($bot['first_name'] ?? '').')');

        $hookResponse = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getWebhookInfo");
        if (! $hookResponse->successful()) {
            $this->error('getWebhookInfo fallito.');

            return self::FAILURE;
        }

        $info = $hookResponse->json('result', []);
        $registeredUrl = (string) ($info['url'] ?? '');
        $lastError = (string) ($info['last_error_message'] ?? '');
        $pending = (int) ($info['pending_update_count'] ?? 0);

        $this->newLine();
        $this->info('Webhook registrato su Telegram:');
        $this->line('  URL: '.($registeredUrl !== '' ? $registeredUrl : '(nessuno — il bot NON riceve messaggi)'));

        if ($registeredUrl === '') {
            $this->warn('  → Esegui: make set-telegram-webhook url='.$appUrl);
        } elseif ($registeredUrl !== $expectedWebhookUrl) {
            $this->warn('  → URL diverso da quello atteso. Riregistra con make set-telegram-webhook.');
        } else {
            $this->info('  → URL corretto.');
        }

        if ($lastError !== '') {
            $this->error('  Ultimo errore Telegram: '.$lastError);
        }

        if ($pending > 0) {
            $this->warn("  Update in coda: {$pending} (Telegram non riceve 200 OK dal server?)");
        }

        $this->newLine();
        $this->comment('Cause frequenti in produzione:');
        $this->line('  1. Webhook mai registrato o URL obsoleto (ngrok, dominio vecchio)');
        $this->line('  2. TELEGRAM_WEBHOOK_SECRET in .env ma webhook registrato senza secret_token (risposta 401)');
        $this->line('  3. config:cache con .env vecchio — dopo deploy: config:clear + config:cache');
        $this->line('  4. APP_URL non HTTPS o non raggiungibile da Internet');
        $this->line('  5. Account non collegato: utente deve fare /start TOKEN dalla WebApp Pro');

        if ($secret !== '' && $registeredUrl !== '') {
            $this->newLine();
            $this->comment('Test locale secret (simula header Telegram):');
            $probe = Http::timeout(10)
                ->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => $secret])
                ->post($expectedWebhookUrl, ['update_id' => 0]);
            if ($probe->status() === 200) {
                $this->info('  POST '.$expectedWebhookUrl.' → 200 OK (secret accettato)');
            } elseif ($probe->status() === 401) {
                $this->error('  POST → 401: secret non accettato dal server (mismatch o proxy che rimuove header)');
            } else {
                $this->warn('  POST → HTTP '.$probe->status().' (verifica routing / firewall / SSL)');
            }
        }

        return self::SUCCESS;
    }
}
