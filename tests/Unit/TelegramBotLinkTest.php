<?php

namespace Tests\Unit;

use App\Support\TelegramBotLink;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TelegramBotLinkTest extends TestCase
{
    #[Test]
    public function it_strips_at_prefix_from_bot_username(): void
    {
        $this->assertSame('finanzamente_bot', TelegramBotLink::normalizeBotUsername('@finanzamente_bot'));
    }

    #[Test]
    public function it_builds_deep_link_with_start_payload(): void
    {
        $link = TelegramBotLink::buildDeepLink('finanzamente_bot', 'abc123XYZ_-');

        $this->assertSame('https://t.me/finanzamente_bot?start=abc123XYZ_-', $link);
    }

    #[Test]
    public function it_rejects_invalid_start_payload_characters(): void
    {
        $this->assertNull(TelegramBotLink::buildDeepLink('finanzamente_bot', 'token.with.dots'));
    }

    #[Test]
    public function generate_start_payload_is_telegram_safe(): void
    {
        $payload = TelegramBotLink::generateStartPayload();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{32}$/', $payload);
    }
}
