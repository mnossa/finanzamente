<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MagazineArticle;
use App\Services\ImageProcessingService;
use Illuminate\Console\Command;

class ConvertArticleImagesToWebp extends Command
{
    protected $signature = 'magazine:convert-images-to-webp
                            {--dry-run : Mostra le immagini da convertire senza modificare nulla}
                            {--limit=0 : Numero massimo di articoli da processare (0 = tutti)}';

    protected $description = 'Converte le immagini di copertina degli articoli in WebP e le ridimensiona a max 1200px';

    public function handle(ImageProcessingService $service): int
    {
        ini_set('memory_limit', '512M');
        $dryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $query = MagazineArticle::whereNotNull('cover_image_path')
            ->where('cover_image_path', 'not like', '%.webp');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $articles = $query->get(['id', 'title', 'cover_image_path']);

        if ($articles->isEmpty()) {
            $this->info('Nessuna immagine da convertire. Tutte già in formato WebP.');

            return self::SUCCESS;
        }

        $this->info("Trovati {$articles->count()} articoli con immagini non-WebP.");

        if ($dryRun) {
            $this->table(['ID', 'Titolo', 'Path attuale'], $articles->map(fn ($a) => [
                $a->id,
                str($a->title)->limit(50),
                $a->cover_image_path,
            ])->toArray());

            $this->warn('Modalità dry-run: nessuna modifica effettuata.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($articles->count());
        $converted = 0;
        $failed = 0;

        $bar->start();

        foreach ($articles as $article) {
            $originalPath = $article->cover_image_path;
            $newPath = $service->convertToWebp($originalPath);

            if ($newPath !== $originalPath) {
                $article->updateQuietly(['cover_image_path' => $newPath]);
                $converted++;
            } else {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Conversione completata: {$converted} convertite, {$failed} fallite.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
