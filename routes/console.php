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
 * Degrada a Base gli utenti con piano Pro scaduto (grace period terminato).
 * Invia email di notifica all'utente.
 */
Schedule::command('plans:process-expirations')->dailyAt('00:05');

/**
 * Invia email di avviso agli utenti Pro il cui piano scade tra 7 o 1 giorno.
 */
Schedule::command('plans:notify-expiring')->dailyAt('08:00');

/**
 * Rileva pattern ricorrenti nelle transazioni ogni lunedì e giovedì alle 01:00.
 * I suggerimenti vengono mostrati all'utente in /rilevamento-ricorrenze.
 */
Schedule::command('recurring:detect')->days([1, 4])->at('01:00');

/**
 * Converte le immagini di copertina degli articoli in WebP e le ridimensiona a max 1200px.
 * Esegue solo su immagini non già in WebP.
 * Può essere eseguito manualmente con opzione --dry-run per vedere quali immagini verrebbero convertite.
 */
Schedule::command('magazine:convert-images-to-webp')->twiceDailyAt(8, 15, 0);

/**
 * Scansiona gli articoli magazine e suggerisce link interni tramite similarità semantica.
 * Esegue ogni domenica alle 03:00 per minimizzare l'impatto sulle performance.
 * I suggerimenti vengono inviati via email all'amministratore (config('mail.admin_address')) in formato leggibile.
 */
Schedule::command('magazine:link-suggestions')->sundays()->at('03:00');

/**
 * Applica retention su eventi consenso GDPR (anonymize + prune) ogni notte.
 */
Schedule::command('consents:enforce-retention')->dailyAt('03:30');

/**
 * Insight di cohort anonimi (Extra vs mediana profili simili) tramite servizio Python.
 * Dati inviati al servizio Python sono solo bucket numerici, senza accesso al DB.
 */
Schedule::command('insights:cohort-analyze')->dailyAt('04:15');

/**
 * Sblocca import rimasti in pending/processing dopo crash worker o timeout (banner «import in corso»).
 */
Schedule::command('transaction-imports:mark-stale')->hourly();
