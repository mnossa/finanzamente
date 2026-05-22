<?php

namespace Tests;

use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Forza SQLite in-memory subito dopo la creazione dell'app,
     * prima che RefreshDatabase possa usare la connessione MySQL
     * (che viene iniettata nel container via .env.docker come variabile OS,
     * quindi non sovrascrivibile da phpunit.xml o .env.testing).
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->app['config']->set('database.default', 'sqlite');
        $this->app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerSqliteRegexpForTests();

        // Evita l'errore "Vite manifest not found" durante i test:
        // non è necessario compilare gli asset frontend per testare la logica backend.
        $this->withoutVite();

        // Seed currencies per tutti i test che ne hanno bisogno
        $this->seed(CurrencySeeder::class);
    }

    /**
     * Registra REGEXP su SQLite per parità con MySQL nei filtri descrizione regex.
     */
    protected function registerSqliteRegexpForTests(): void
    {
        if ($this->app['db']->connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $this->app['db']->connection()->getPdo();
        if (! method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }

        $pdo->sqliteCreateFunction('regexp', static function (?string $pattern, ?string $value): int {
            if ($value === null || $pattern === null) {
                return 0;
            }

            $previous = set_error_handler(static fn () => true);
            try {
                $result = @preg_match('@(?:'.$pattern.')@u', $value);
            } finally {
                if ($previous !== null) {
                    set_error_handler($previous);
                } else {
                    restore_error_handler();
                }
            }

            return $result === 1 ? 1 : 0;
        }, 2);
    }
}
