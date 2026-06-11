<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * One-shot bootstrap per il rilascio Formula Widget Platform.
 * Eseguito una sola volta con migrate; non va ripetuto a ogni deploy (vedi entrypoint / DEPLOY.md).
 *
 * Equivalente manuale: php artisan formula-widgets:release-bootstrap
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('formula-widgets:release-bootstrap');
    }

    public function down(): void
    {
        // Bootstrap dati non reversibile.
    }
};
