<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * Genera automaticamente il file sitemap.xml con le rotte pubbliche dell'app.
 *
 * Eseguire manualmente: php artisan sitemap:generate
 * Schedulato ogni domenica alle 02:00 tramite routes/console.php
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Genera il file public/sitemap.xml con le rotte pubbliche';

    public function handle(): int
    {
        $this->info('Generazione sitemap in corso…');

        Sitemap::create()
            // Homepage
            ->add(
                Url::create('/')
                    ->setLastModificationDate(Carbon::now())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(1.0)
            )
            // Login
            ->add(
                Url::create('/login')
                    ->setLastModificationDate(Carbon::now())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority(0.7)
            )
            // Registrazione
            ->add(
                Url::create('/register')
                    ->setLastModificationDate(Carbon::now())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority(0.8)
            )
            // Password dimenticata
            ->add(
                Url::create('/forgot-password')
                    ->setLastModificationDate(Carbon::now())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                    ->setPriority(0.3)
            )
            ->writeToFile(public_path('sitemap.xml'));

        $this->info('sitemap.xml generato correttamente in ' . public_path('sitemap.xml'));

        return self::SUCCESS;
    }
}
