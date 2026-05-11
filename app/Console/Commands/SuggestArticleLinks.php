<?php

namespace App\Console\Commands;

use App\Models\LinkSuggestion;
use App\Models\LinkSuggestionRun;
use App\Models\MagazineArticle;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
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

        $pythonServicesUrl = rtrim((string) config('services.python_services.url'), '/');

        // Verifica che il servizio Python sia raggiungibile (max 3 tentativi con backoff)
        $maxRetries = 3;
        $retryDelay = 5;
        $lastError = null;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $health = Http::timeout(5)->get("{$pythonServicesUrl}/health");
                if ($health->successful()) {
                    $lastError = null;
                    break;
                }
                $lastError = new \RuntimeException("HTTP {$health->status()}");
            } catch (\Throwable $e) {
                $lastError = $e;
            }
            if ($attempt < $maxRetries) {
                $this->warn("Tentativo {$attempt}/{$maxRetries}: servizio Python non raggiungibile, attendo {$retryDelay}s...");
                sleep($retryDelay);
            }
        }
        if ($lastError !== null) {
            $this->error("Servizio Python non raggiungibile ({$pythonServicesUrl}): ".$lastError->getMessage());
            Log::error('magazine:link-suggestions — servizio Python non raggiungibile', ['error' => $lastError->getMessage()]);

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

        // Costruisce la mappa dei link già presenti per ogni articolo (dal contenuto corrente)
        $alreadyLinked = [];
        foreach ($articles as $a) {
            $alreadyLinked[(string) $a->id] = self::extractLinkedSlugs($a->content);
        }

        // Auto-rileva i suggerimenti pending che l'editore ha nel frattempo implementato
        $implementedCount = $this->detectImplementedSuggestions($articles, $alreadyLinked);
        if ($implementedCount > 0) {
            $this->info("Suggerimenti implementati rilevati in questo run: {$implementedCount}");
        }

        // Aggiunge alla mappa di esclusione i target degli eventuali suggerimenti ancora pending
        // in storia, per evitare di ri-proporre coppie già suggerite e non ancora decise
        $alreadyLinked = $this->mergeHistoryIntoAlreadyLinked($alreadyLinked, $articles);

        // Chiama il servizio Python
        try {
            $response = Http::timeout(120)->post("{$pythonServicesUrl}/batch-suggest", [
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
            $this->error('Errore chiamata servizio Python: '.$e->getMessage());
            Log::error('magazine:link-suggestions — errore servizio Python', ['error' => $e->getMessage()]);

            return 1;
        }

        $data = $response->json();
        $rawSuggestions = $data['suggestions'] ?? [];

        // Arricchisce con i modelli Eloquent, applicando:
        // - limite globale
        // - deduplicazione esplicita coppie (source_id, target_id) come guardrail aggiuntivo
        // - verifica che il target non sia già linkato nel source (belt-and-suspenders lato PHP)
        $articleIndex = $articles->keyBy('id');
        $suggestions = [];
        $seenPairs = [];

        foreach ($rawSuggestions as $s) {
            if (count($suggestions) >= $maxSuggestions) {
                break;
            }

            $pairKey = $s['source_id'].'-'.$s['target_id'];
            if (isset($seenPairs[$pairKey])) {
                continue;
            }
            $seenPairs[$pairKey] = true;

            $source = $articleIndex->get($s['source_id']);
            $target = $articleIndex->get($s['target_id']);
            if (! $source || ! $target) {
                continue;
            }

            // Guardrail PHP: il target non deve essere già linkato nel source
            $sourceLinkedSlugs = $alreadyLinked[(string) $source->id] ?? [];
            if (in_array($target->slug, $sourceLinkedSlugs, true)) {
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

        // Persiste run e nuovi suggerimenti nella storia
        $this->saveRun($suggestions, $implementedCount, $duration, $data['articles_processed'] ?? 0);

        try {
            $this->sendMail($suggestions, $duration, $memory, $data['articles_processed'] ?? 0, $implementedCount);
        } catch (\Throwable $e) {
            Log::error('Errore invio mail suggerimenti SEO: '.$e->getMessage());
            $this->error('Errore invio mail: '.$e->getMessage());
        }

        $this->info(
            'Suggerimenti generati: '.count($suggestions).
                ' | Implementati rilevati: '.$implementedCount.
                ' | Tempo: '.round($duration, 2).'s'.
                ' | Memoria: '.$memory.'MB'
        );

        return 0;
    }

    /**
     * Auto-rileva quali suggerimenti "pending" sono stati implementati dall'editore:
     * un suggerimento è implementato se lo slug del target è ora presente tra i link del source.
     *
     * @param  Collection<int, MagazineArticle>  $articles
     * @param  array<string, string[]>  $alreadyLinked
     */
    private function detectImplementedSuggestions(Collection $articles, array $alreadyLinked): int
    {
        $articleById = $articles->keyBy('id');
        $implemented = 0;

        $pending = LinkSuggestion::pending()
            ->whereIn('source_article_id', $articles->pluck('id'))
            ->with('targetArticle:id,slug')
            ->get();

        foreach ($pending as $suggestion) {
            $source = $articleById->get($suggestion->source_article_id);
            $target = $suggestion->targetArticle;

            if (! $source || ! $target) {
                continue;
            }

            $linkedSlugs = $alreadyLinked[(string) $source->id] ?? [];
            if (in_array($target->slug, $linkedSlugs, true)) {
                $suggestion->markImplemented();
                $implemented++;
            }
        }

        return $implemented;
    }

    /**
     * Aggiunge agli slug già esclusi per ogni source i target dei suggerimenti ancora "pending",
     * per evitare che il servizio Python ri-proponga coppie già suggerite e non ancora risolte.
     *
     * @param  array<string, string[]>  $alreadyLinked
     * @param  Collection<int, MagazineArticle>  $articles
     * @return array<string, string[]>
     */
    private function mergeHistoryIntoAlreadyLinked(array $alreadyLinked, Collection $articles): array
    {
        $pending = LinkSuggestion::pending()
            ->whereIn('source_article_id', $articles->pluck('id'))
            ->with('targetArticle:id,slug')
            ->get();

        foreach ($pending as $suggestion) {
            $key = (string) $suggestion->source_article_id;
            $targetSlug = $suggestion->targetArticle?->slug;

            if ($targetSlug === null) {
                continue;
            }

            if (! isset($alreadyLinked[$key])) {
                $alreadyLinked[$key] = [];
            }

            if (! in_array($targetSlug, $alreadyLinked[$key], true)) {
                $alreadyLinked[$key][] = $targetSlug;
            }
        }

        return $alreadyLinked;
    }

    /**
     * Persiste il run corrente e i nuovi suggerimenti nel database per lo storico.
     */
    private function saveRun(array $suggestions, int $implementedCount, float $duration, int $articlesProcessed): LinkSuggestionRun
    {
        $run = LinkSuggestionRun::create([
            'ran_at' => now(),
            'articles_processed' => $articlesProcessed,
            'suggestions_count' => count($suggestions),
            'implemented_count' => $implementedCount,
            'duration_seconds' => round($duration, 2),
        ]);

        foreach ($suggestions as $s) {
            LinkSuggestion::create([
                'run_id' => $run->id,
                'source_article_id' => $s['source']->id,
                'target_article_id' => $s['target']->id,
                'score' => $s['score'],
                'snippet' => $s['snippet'] ?: null,
                'status' => 'pending',
            ]);
        }

        return $run;
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

    private function sendMail(array $suggestions, float $duration, float $memory, int $articlesProcessed, int $implementedCount): void
    {
        $to = env('MAGAZINE_ADMIN_EMAIL') ?: config('mail.admin_address', config('mail.from.address'));
        $count = count($suggestions);

        $body = "Trovati {$count} nuovi suggerimenti di link interni tra articoli magazine (similarità semantica).\n";
        $body .= "Articoli analizzati: {$articlesProcessed} | Tempo: ".round($duration, 2)."s | Memoria: {$memory}MB\n";
        if ($implementedCount > 0) {
            $body .= "Suggerimenti implementati rilevati automaticamente: {$implementedCount}\n";
        }
        $body .= "\n";

        foreach ($suggestions as $s) {
            $score = number_format($s['score'] * 100, 1);
            $body .= "- Nell'articolo: [{$s['source']->title}](".url('/magazine/'.$s['source']->slug).")\n";
            $body .= "  suggerisci di linkare: [{$s['target']->title}](".url('/magazine/'.$s['target']->slug).") (similarità: {$score}%)\n";
            if ($s['snippet']) {
                $body .= '  → Contesto: "'.$s['snippet']."\"\n";
            }
            $body .= "\n";
        }

        // Statistiche globali storico
        $totalPending = LinkSuggestion::pending()->count();
        $totalImplemented = LinkSuggestion::implemented()->count();
        $body .= "--- Storico complessivo ---\n";
        $body .= "In attesa: {$totalPending} | Implementati: {$totalImplemented}\n";

        Mail::raw($body, function ($m) use ($to, $count) {
            $m->to($to)
                ->subject("[Finanzamente] Suggerimenti link interni magazine ({$count} nuovi)");
        });
    }
}
