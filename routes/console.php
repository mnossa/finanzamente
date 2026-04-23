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
Schedule::command('magazine:convert-images-to-webp')->twiceDailyAt(8,15,0);

// Suggerimenti link interni magazine per SEO: ogni 2 settimane, domenica alle 03:00
Schedule::command('magazine:link-suggestions')->sundays()->twiceMonthly()->at('03:00');
