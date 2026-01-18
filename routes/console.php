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
