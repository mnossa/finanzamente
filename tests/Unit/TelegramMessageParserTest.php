<?php

namespace Tests\Unit;

use App\Http\Controllers\TelegramWebhookController;
use App\Services\CurrencyConverter;
use App\Services\TelegramService;
use App\Services\VisionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TelegramMessageParserTest extends TestCase
{
    private function parse(string $text): array
    {
        $controller = new TelegramWebhookController(
            $this->createMock(TelegramService::class),
            $this->createMock(VisionService::class),
            $this->createMock(CurrencyConverter::class),
        );
        $method = new ReflectionMethod(TelegramWebhookController::class, 'parseTextMessage');
        $method->setAccessible(true);

        return $method->invoke($controller, $text);
    }

    public function test_parses_amount_with_trailing_dot_before_description(): void
    {
        $parsed = $this->parse('15. Pizza');

        $this->assertSame(15.0, $parsed['amount']);
        $this->assertSame('Pizza', $parsed['description']);
    }

    public function test_parses_amount_with_comma_decimal(): void
    {
        $parsed = $this->parse('8,50 Bar');

        $this->assertSame(8.5, $parsed['amount']);
        $this->assertSame('Bar', $parsed['description']);
    }

    public function test_parses_description_before_amount(): void
    {
        $parsed = $this->parse('Pizza 15,50');

        $this->assertSame(15.5, $parsed['amount']);
        $this->assertSame('Pizza', $parsed['description']);
    }
}
