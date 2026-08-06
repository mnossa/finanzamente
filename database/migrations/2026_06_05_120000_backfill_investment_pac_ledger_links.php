<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * One-shot backfill dopo intro di InvestmentObserver e coerenza investimenti/ledger.
 * Eseguito una sola volta con migrate; non va ripetuto a ogni deploy (vedi entrypoint).
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('investment-pacs:realign-movements');
        Artisan::call('investment-pacs:sync-transactions');
    }

    public function down(): void
    {
        // Backfill dati non reversibile.
    }
};
