<?php

namespace App\Console\Commands;

use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
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

    /**
     * Rotte pubbliche sempre incluse nella sitemap.
     * Formato: [ 'nome_rotta' => [changefreq, priority] ]
     *
     * Per aggiungere una nuova pagina pubblica, aggiungere una voce qui.
     */
    private array $routes = [
        // Core
        'home' => [Url::CHANGE_FREQUENCY_WEEKLY,  1.0],
        'login' => [Url::CHANGE_FREQUENCY_MONTHLY, 0.7],
        // Landing page
        'landing.investitori' => [Url::CHANGE_FREQUENCY_MONTHLY, 0.8],
        'landing.famiglie' => [Url::CHANGE_FREQUENCY_MONTHLY, 0.8],
        'landing.freelance' => [Url::CHANGE_FREQUENCY_MONTHLY, 0.8],
        'landing.lavoratori' => [Url::CHANGE_FREQUENCY_MONTHLY, 0.8],
        'landing.pianificatori' => [Url::CHANGE_FREQUENCY_MONTHLY, 0.8],
        'landing.tech-savvy' => [Url::CHANGE_FREQUENCY_MONTHLY, 0.8],
        'landing.crescita' => [Url::CHANGE_FREQUENCY_MONTHLY, 0.8],
    ];

    /**
     * Rotte aggiunte solo a pre-lancio disattivo.
     */
    private array $postLaunchRoutes = [
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

        $activeRoutes = config('prelaunch.enabled')
            ? $this->routes
            : array_merge($this->routes, $this->postLaunchRoutes);

        foreach ($activeRoutes as $name => [$changefreq, $priority]) {
            $sitemap->add(
                Url::create(route($name))
                    ->setLastModificationDate($now)
                    ->setChangeFrequency($changefreq)
                    ->setPriority($priority)
            );
        }

        // Magazine — index e pagine di categoria
        $sitemap->add(
            Url::create(route('magazine.index'))
                ->setLastModificationDate($now)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(0.9)
        );

        MagazineCategory::withCount('publishedArticles')
            ->having('published_articles_count', '>', 0)
            ->get()
            ->each(function ($category) use ($sitemap, $now) {
                $sitemap->add(
                    Url::create(route('magazine.category', $category->slug))
                        ->setLastModificationDate($now)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.7)
                );
            });

        // Magazine — singoli articoli pubblicati
        MagazineArticle::published()
            ->select(['slug', 'published_at', 'updated_at'])
            ->orderByDesc('published_at')
            ->each(function ($article) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('magazine.show', $article->slug))
                        ->setLastModificationDate($article->updated_at ?? $article->published_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setPriority(0.8)
                );
            });

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('sitemap.xml generato correttamente in '.public_path('sitemap.xml'));

        return self::SUCCESS;
    }
}
