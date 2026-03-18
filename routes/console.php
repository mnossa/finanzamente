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
 * Rigenera la sitemap.xml ogni domenica alle 02:00.
 * Per aggiungere nuove rotte pubbliche, edita GenerateSitemap::handle().
 */
Schedule::command('sitemap:generate')->weeklyOn(0, '02:00');

/**
 * Rimuove i file di log più vecchi di 30 giorni ogni notte alle 03:00.
 */
Schedule::command('logs:prune')->dailyAt('03:00');
