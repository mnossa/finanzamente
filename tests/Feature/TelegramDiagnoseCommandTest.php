<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramDiagnoseCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function diagnose_fails_when_bot_token_missing(): void
    {
        config(['services.telegram.bot_token' => '']);

        $this->artisan('telegram:diagnose')
            ->expectsOutputToContain('TELEGRAM_BOT_TOKEN mancante')
            ->assertFailed();
    }

    #[Test]
    public function diagnose_succeeds_when_bot_and_webhook_are_valid(): void
    {
        config([
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.bot_username' => 'finanzamente_bot',
            'services.telegram.webhook_secret' => '',
            'app.url' => 'https://finanzamente.test',
        ]);

        Http::fake([
            'api.telegram.org/bottest-token/getMe' => Http::response([
                'ok' => true,
                'result' => ['username' => 'finanzamente_bot', 'first_name' => 'Finanzamente'],
            ]),
            'api.telegram.org/bottest-token/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://finanzamente.test/telegram/webhook',
                    'pending_update_count' => 0,
                ],
            ]),
        ]);

        $this->artisan('telegram:diagnose')
            ->expectsOutputToContain('Bot attivo')
            ->assertSuccessful();
    }
}
