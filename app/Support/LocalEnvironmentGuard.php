<?php

namespace App\Support;

use RuntimeException;

/**
 * Limita comandi artisan distruttivi o di sviluppo a ambienti locali.
 */
class LocalEnvironmentGuard
{
    /** @var list<string> */
    public const ALLOWED_ENVIRONMENTS = ['local', 'development', 'testing'];

    public static function isLocalDevelopment(): bool
    {
        return app()->environment(self::ALLOWED_ENVIRONMENTS);
    }

    public static function assertLocalDevelopment(string $commandName): void
    {
        if (self::isLocalDevelopment()) {
            return;
        }

        throw new RuntimeException(
            "Il comando {$commandName} è disponibile solo in ambiente local/development/testing."
        );
    }
}
