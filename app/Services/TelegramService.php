<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TelegramService
 *
 * Gestisce l'interazione con le API di Telegram Bot.
 * Invia messaggi di feedback all'utente dopo la ricezione di messaggi/foto.
 */
class TelegramService
{
    private string $baseUrl;

    public function __construct()
    {
        $token = trim((string) config('services.telegram.bot_token', ''));
        $this->baseUrl = $token !== ''
            ? "https://api.telegram.org/bot{$token}"
            : '';
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '';
    }

    /**
     * Invia un messaggio di testo a una chat Telegram.
     */
    public function sendMessage(string $chatId, string $text, string $parseMode = 'HTML'): bool
    {
        if (! $this->isConfigured()) {
            Log::error('TelegramService: TELEGRAM_BOT_TOKEN non configurato, impossibile inviare messaggi');

            return false;
        }

        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
            ]);

            if (! $response->successful()) {
                Log::warning('TelegramService: sendMessage rifiutato da API', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('TelegramService: impossibile inviare messaggio', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Scarica un file da Telegram e ne restituisce il contenuto.
     * Necessario per le foto inviate via bot.
     */
    public function downloadFile(string $fileId): ?string
    {
        try {
            // Recupera il percorso del file
            $response = Http::timeout(10)->get("{$this->baseUrl}/getFile", [
                'file_id' => $fileId,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $filePath = $response->json('result.file_path');
            if (! $filePath) {
                return null;
            }

            $token = config('services.telegram.bot_token');
            $fileUrl = "https://api.telegram.org/file/bot{$token}/{$filePath}";

            $fileResponse = Http::timeout(30)->get($fileUrl);

            return $fileResponse->successful() ? $fileResponse->body() : null;
        } catch (\Throwable $e) {
            Log::warning('TelegramService: impossibile scaricare file', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Imposta il webhook URL per il bot Telegram.
     * Da invocare una sola volta in fase di setup.
     */
    public function setWebhook(string $webhookUrl, ?string $secretToken = null): bool
    {
        if (! $this->isConfigured()) {
            Log::error('TelegramService: TELEGRAM_BOT_TOKEN non configurato, impossibile impostare webhook');

            return false;
        }

        try {
            $payload = [
                'url' => $webhookUrl,
                'allowed_updates' => ['message'],
            ];

            if ($secretToken !== null && $secretToken !== '') {
                $payload['secret_token'] = $secretToken;
            }

            $response = Http::timeout(10)->post("{$this->baseUrl}/setWebhook", $payload);

            return $response->successful() && $response->json('ok') === true;
        } catch (\Throwable $e) {
            Log::error('TelegramService: impossibile impostare webhook', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
