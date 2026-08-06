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
 * Schedulato ogni notte alle 02:00 tramite routes/console.php
 */
class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Genera il file public/sitemap.xml con le rotte pubbliche';

    /**
     * Rotte pubbliche sempre incluse nella sitemap.
     * Formato: [ 'nome_rotta' => [changefreq, priority] ]
     *
     * Per aggiungere una nuova pagina pubblica, aggiungere una voce qui.
     */
    private array $routes = [
        'home' => [Url::CHANGE_FREQUENCY_WEEKLY,  1.0],
        'login' => [Url::CHANGE_FREQUENCY_MONTHLY, 0.7],
        'register' => [Url::CHANGE_FREQUENCY_MONTHLY, 0.8],
        'legal.privacy' => [Url::CHANGE_FREQUENCY_YEARLY,  0.3],
        'legal.cookies' => [Url::CHANGE_FREQUENCY_YEARLY,  0.3],
        'legal.terms' => [Url::CHANGE_FREQUENCY_YEARLY,  0.3],
    ];

    public function handle(): int
    {
        $this->info('Generazione sitemap in corso…');

        $now = Carbon::now();
        $sitemap = Sitemap::create();

        foreach ($this->routes as $name => [$changefreq, $priority]) {
            $sitemap->add(
                Url::create(route($name))
                    ->setLastModificationDate($now)
                    ->setChangeFrequency($changefreq)
                    ->setPriority($priority)
            );
        }

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('sitemap.xml generato correttamente in '.public_path('sitemap.xml'));

        return self::SUCCESS;
    }
}
