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

        // Evita l'errore "Vite manifest not found" durante i test:
        // non è necessario compilare gli asset frontend per testare la logica backend.
        $this->withoutVite();

        // Seed currencies per tutti i test che ne hanno bisogno
        $this->seed(CurrencySeeder::class);
    }
}
