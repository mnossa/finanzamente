<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Helper per deep link Telegram (parametro start del bot).
 *
 * @see https://core.telegram.org/bots/features#deep-linking
 */
final class TelegramBotLink
{
    /**
     * Caratteri ammessi nel payload start: A-Z, a-z, 0-9, _, -
     */
    public static function generateStartPayload(): string
    {
        return str_replace('-', '', (string) Str::uuid());
    }

    public static function normalizeBotUsername(?string $username): ?string
    {
        $username = ltrim(trim((string) $username), '@');

        return $username !== '' ? $username : null;
    }

    /**
     * URL https://t.me/{bot}?start={payload} — senza percent-encoding sul payload.
     */
    public static function buildDeepLink(?string $botUsername, ?string $startPayload): ?string
    {
        $bot = self::normalizeBotUsername($botUsername);
        $payload = trim((string) $startPayload);

        if ($bot === null || $payload === '') {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $payload)) {
            return null;
        }

        return "https://t.me/{$bot}?start={$payload}";
    }
}
