<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Schedule per generare automaticamente le transazioni ricorrenti
 * ogni giorno alle 00:01
 */
Schedule::command('recurring:generate')->dailyAt('00:01');

Schedule::command('recurring:remind')->dailyAt('08:00');
Schedule::command('upcoming-due:notify-weekly')->weeklyOn(1, '08:30');

/**
 * Rigenera la sitemap.xml ogni notte alle 02:00.
 * Per aggiungere nuove rotte pubbliche, edita GenerateSitemap::handle().
 */
Schedule::command('sitemap:generate')->dailyAt('02:00');

/**
 * Rimuove i file di log più vecchi di 30 giorni ogni notte alle 03:00.
 */
Schedule::command('logs:prune')->dailyAt('03:00');

/**
 * Rileva pattern ricorrenti nelle transazioni ogni lunedì e giovedì alle 01:00.
 * I suggerimenti vengono mostrati all'utente in /rilevamento-ricorrenze.
 */
Schedule::command('recurring:detect')->days([1, 4])->at('01:00');

/**
 * Applica retention su eventi consenso GDPR (anonymize + prune) ogni notte.
 */
Schedule::command('consents:enforce-retention')->dailyAt('03:30');

/**
 * Insight di cohort anonimi (Extra vs mediana profili simili) tramite servizio Python.
 * Dati inviati al servizio Python sono solo bucket numerici, senza accesso al DB.
 */
Schedule::command('insights:cohort-analyze')->dailyAt('04:15');
Schedule::command('notifications:monthly-spending')->dailyAt('23:40');
Schedule::command('notifications:household-insights')->dailyAt('09:10');

Schedule::command('investment-pacs:run')->dailyAt('00:20');
Schedule::command('investment-pacs:remind')->dailyAt('08:15');

/**
 * Sblocca import rimasti in pending/processing dopo crash worker o timeout (banner «import in corso»).
 */
Schedule::command('transaction-imports:mark-stale --scheduled')->hourly();
Schedule::command('transactions:detect-duplicates')->dailyAt('01:30');
