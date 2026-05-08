<?php

namespace App\Console\Commands;

use App\Models\MagazineArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use League\CommonMark\CommonMarkConverter;

class SuggestArticleLinks extends Command
{
    protected $signature = 'magazine:link-suggestions'
        .' {--max=100 : Numero massimo di suggerimenti totali}'
        .' {--per-article=5 : Max suggerimenti per articolo}'
        .' {--min-score=0.55 : Score minimo di similarità semantica (0-1)}'
        .' {--max-score=0.95 : Score massimo: oltre questo valore considera duplicato e scarta}';

    protected $description = 'Scansiona gli articoli magazine e suggerisce link interni tramite similarità semantica (output via email)';

    public function handle(): int
    {
        $start = microtime(true);
        $maxSuggestions = (int) $this->option('max');
        $maxPerArticle = (int) $this->option('per-article');
        $minScore = (float) $this->option('min-score');
        $maxScore = (float) $this->option('max-score');

        $linkerUrl = rtrim(env('PYTHON_LINKER_URL', 'http://127.0.0.1:8000'), '/');

        // Verifica che il servizio Python sia raggiungibile (max 3 tentativi con backoff)
        $maxRetries = 3;
        $retryDelay = 5; // secondi
        $lastError = null;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $health = Http::timeout(5)->get("{$linkerUrl}/health");
                if ($health->successful()) {
                    $lastError = null;
                    break;
                }
                $lastError = new \RuntimeException("HTTP {$health->status()}");
            } catch (\Throwable $e) {
                $lastError = $e;
            }
            if ($attempt < $maxRetries) {
                $this->warn("Tentativo {$attempt}/{$maxRetries}: python-linker non raggiungibile, attendo {$retryDelay}s...");
                sleep($retryDelay);
            }
        }
        if ($lastError !== null) {
            $this->error("Servizio python-linker non raggiungibile ({$linkerUrl}): ".$lastError->getMessage());
            Log::error('magazine:link-suggestions — python-linker non raggiungibile', ['error' => $lastError->getMessage()]);

            return 1;
        }

        $converter = new CommonMarkConverter;

        // Carica tutti gli articoli pubblicati con contenuto sufficiente
        $articles = MagazineArticle::published()
            ->whereRaw('LENGTH(content) > 300')
            ->orderBy('published_at', 'desc')
            ->select(['id', 'slug', 'title', 'content'])
            ->get();

        if ($articles->count() < 2) {
            $this->info('Meno di 2 articoli pubblicati, nessun suggerimento possibile.');

            return 0;
        }

        $this->info("Articoli caricati: {$articles->count()}. Invio al servizio semantico...");

        // Prepara payload: testo plain di ogni articolo
        $payload = $articles->map(fn ($a) => [
            'id' => $a->id,
            'slug' => $a->slug,
            'title' => $a->title,
            'text' => $this->toPlainText($a->content, $converter),
        ])->values()->all();

        // Costruisce la mappa dei link già presenti per ogni articolo
        $alreadyLinked = [];
        foreach ($articles as $a) {
            $alreadyLinked[(string) $a->id] = self::extractLinkedSlugs($a->content);
        }

        // Chiama il servizio Python
        try {
            $response = Http::timeout(120)->post("{$linkerUrl}/batch-suggest", [
                'articles' => $payload,
                'top_k' => $maxPerArticle,
                'min_score' => $minScore,
                'max_score' => $maxScore,
                'already_linked' => $alreadyLinked,
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException("HTTP {$response->status()}: ".$response->body());
            }
        } catch (\Throwable $e) {
            $this->error('Errore chiamata python-linker: '.$e->getMessage());
            Log::error('magazine:link-suggestions — errore python-linker', ['error' => $e->getMessage()]);

            return 1;
        }

        $data = $response->json();
        $rawSuggestions = $data['suggestions'] ?? [];

        // Limita al massimo globale e arricchisce con i modelli Eloquent
        $articleIndex = $articles->keyBy('id');
        $suggestions = [];

        foreach ($rawSuggestions as $s) {
            if (count($suggestions) >= $maxSuggestions) {
                break;
            }

            $source = $articleIndex->get($s['source_id']);
            $target = $articleIndex->get($s['target_id']);
            if (! $source || ! $target) {
                continue;
            }

            $suggestions[] = [
                'source' => $source,
                'target' => $target,
                'score' => $s['score'],
                'snippet' => $s['snippet'] ?? '',
            ];
        }

        $duration = microtime(true) - $start;
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 1);

        try {
            $this->sendMail($suggestions, $duration, $memory, $data['articles_processed'] ?? 0);
        } catch (\Throwable $e) {
            Log::error('Errore invio mail suggerimenti SEO: '.$e->getMessage());
            $this->error('Errore invio mail: '.$e->getMessage());
        }

        $this->info(
            'Suggerimenti generati: '.count($suggestions).
                ' | Tempo: '.round($duration, 2).'s'.
                ' | Memoria: '.$memory.'MB'
        );

        return 0;
    }

    /**
     * Converte il contenuto Markdown in testo plain (senza HTML e senza tag).
     */
    private function toPlainText(string $markdown, CommonMarkConverter $converter): string
    {
        $html = (string) $converter->convert($markdown);

        return trim(strip_tags($html));
    }

    /**
     * Estrae gli slug di articoli magazine già linkati nel markdown sorgente.
     *
     * Cattura tutte le forme realistiche di link interno:
     *  - markdown relativo:  [testo](/magazine/slug)
     *  - markdown con query/anchor:  [testo](/magazine/slug?utm=...) oppure (/magazine/slug#sezione)
     *  - markdown assoluto:  [testo](https://finanzamente.it/magazine/slug)
     *  - HTML inline:  <a href="/magazine/slug"> o href="https://..."
     */
    public static function extractLinkedSlugs(string $markdown): array
    {
        $slugs = [];

        // Markdown link: [testo](URL) — URL può iniziare con http(s)://host oppure /
        preg_match_all(
            '~\[[^\]]+\]\(\s*(?:https?://[^/\s)]+)?/magazine/([a-z0-9\-]+)(?:[/?#][^)\s]*)?\s*\)~i',
            $markdown,
            $mdMatches
        );

        // HTML href="..."
        preg_match_all(
            '~href\s*=\s*[\'"](?:https?://[^/\'"\s]+)?/magazine/([a-z0-9\-]+)(?:[/?#][^\'"]*)?[\'"]~i',
            $markdown,
            $htmlMatches
        );

        foreach (array_merge($mdMatches[1] ?? [], $htmlMatches[1] ?? []) as $slug) {
            $slugs[] = strtolower($slug);
        }

        return array_values(array_unique($slugs));
    }

    private function sendMail(array $suggestions, float $duration, float $memory, int $articlesProcessed): void
    {
        $to = env('MAGAZINE_ADMIN_EMAIL') ?: config('mail.admin_address', config('mail.from.address'));
        $count = count($suggestions);

        $body = "Trovati {$count} suggerimenti di link interni tra articoli magazine (similarità semantica).\n";
        $body .= "Articoli analizzati: {$articlesProcessed} | Tempo: ".round($duration, 2)."s | Memoria: {$memory}MB\n\n";

        foreach ($suggestions as $s) {
            $score = number_format($s['score'] * 100, 1);
            $body .= "- Nell'articolo: [{$s['source']->title}](".url('/magazine/'.$s['source']->slug).")\n";
            $body .= "  suggerisci di linkare: [{$s['target']->title}](".url('/magazine/'.$s['target']->slug).") (similarità: {$score}%)\n";
            if ($s['snippet']) {
                $body .= '  → Contesto: "'.$s['snippet']."\"\n";
            }
            $body .= "\n";
        }

        Mail::raw($body, function ($m) use ($to, $count) {
            $m->to($to)
                ->subject("[Finanzamente] Suggerimenti link interni magazine ({$count})");
        });
    }
}
