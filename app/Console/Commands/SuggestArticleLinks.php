<?php

namespace App\Console\Commands;

use App\Models\MagazineArticle;
use App\Console\Commands\StopWords;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

class SuggestArticleLinks extends Command
{
    protected $signature = 'magazine:link-suggestions {--max=100 : Numero massimo di suggerimenti totali}'
        . ' {--per-article=5 : Max suggerimenti per articolo}';
    protected $description = 'Scansiona gli articoli magazine e suggerisce link interni per la SEO (output via email)';

    public function handle()
    {
        $start = microtime(true);
        $maxSuggestions = (int) $this->option('max');
        $maxPerArticle = (int) $this->option('per-article');
        $suggestions = [];
        $total = 0;
        $converter = new CommonMarkConverter();

        // Carica solo articoli pubblicati e "lunghi"
        MagazineArticle::published()
            ->whereRaw('CHAR_LENGTH(content) > 300')
            ->orderBy('published_at', 'desc')
            ->chunk(20, function ($articles) use (&$suggestions, &$total, $maxSuggestions, $maxPerArticle, $converter) {
                foreach ($articles as $article) {
                    if ($total >= $maxSuggestions) return false;
                    $matches = $this->findSuggestionsFor($article, $maxPerArticle, $converter);
                    foreach ($matches as $s) {
                        if ($total < $maxSuggestions) {
                            $suggestions[] = $s;
                            $total++;
                        }
                    }
                }
            });

        $duration = microtime(true) - $start;
        $memory = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
        try {
            $this->sendMail($suggestions, $duration, $memory);
        } catch (\Throwable $e) {
            Log::error('Errore invio mail suggerimenti SEO: ' . $e->getMessage());
            $this->error('Errore invio mail: ' . $e->getMessage());
        }
        $this->info("Suggerimenti generati: " . count($suggestions) . " | Tempo: " . round($duration, 2) . "s | Memoria: {$memory}MB");
        return 0;
    }

    private function findSuggestionsFor($article, $maxPerArticle, $converter)
    {
        $suggestions = [];
        $content = $article->content;
        $textLower = Str::lower(strip_tags($converter->convert($content)));
        $alreadyLinked = $this->extractLinkedSlugs($content);
        $stopwords = StopWords::$it;
        $count = 0;
        // Batch candidates per performance
        $breakChunk = false;
        MagazineArticle::published()
            ->where('id', '!=', $article->id)
            ->select(['id', 'slug', 'title'])
            ->chunk(30, function ($candidates) use (&$suggestions, &$count, $maxPerArticle, $alreadyLinked, $stopwords, $textLower, $article, &$breakChunk, $content, $converter) {
                foreach ($candidates as $target) {
                    if ($count >= $maxPerArticle) {
                        $breakChunk = true;
                        break;
                    }
                    if (in_array($target->slug, $alreadyLinked)) continue;
                    $title = Str::lower($target->title);
                    // Escludi se titolo è una stopword o troppo corto
                    if (in_array($title, $stopwords) || Str::length($title) < 4) continue;
                    $pattern = '/\b' . preg_quote($title, '/') . '\b/u';
                    if (preg_match($pattern, $textLower, $match, PREG_OFFSET_CAPTURE)) {
                        // Score raffinato: penalità per occorrenze, lunghezza, posizione, TF-IDF base
                        $score = 100;
                        $occurrences = preg_match_all($pattern, $textLower);
                        if ($occurrences > 3) $score -= 20;
                        if ($occurrences > 6) $score -= 40;
                        if ($score < 0) $score = 0;
                        // Penalità se titolo molto comune
                        if (Str::length($title) < 6) $score -= 20;
                        // Penalità se match solo in fondo al testo
                        $pos = $match[0][1];
                        if ($pos > strlen($textLower) * 0.8) $score -= 10;
                        // Bonus se match in heading (##, ###)
                        if (preg_match('/^#+ .*' . preg_quote($target->title, '/') . '/im', $content)) $score += 10;
                        if ($score < 0) $score = 0;
                        if ($score >= 80) {
                            // Trova la frase di contesto (snippet frase intera)
                            $plain = strip_tags($converter->convert($content));
                            $plainLower = Str::lower($plain);
                            $matchPos = strpos($plainLower, $title);
                            $before = strrpos(substr($plainLower, 0, $matchPos), '.');
                            $after = strpos($plainLower, '.', $matchPos);
                            $start = $before !== false ? $before + 1 : max(0, $matchPos - 60);
                            $end = $after !== false ? $after + 1 : min(strlen($plain), $matchPos + strlen($title) + 60);
                            $snippet = trim(substr($plain, $start, $end - $start));
                            // Evidenzia la parola chiave (maiuscolo tra virgolette)
                            $snippet = preg_replace('/(' . preg_quote($target->title, '/') . ')/iu', '"$1"', $snippet);
                            $suggestions[] = [
                                'source' => $article,
                                'target' => $target,
                                'match' => $target->title,
                                'score' => $score,
                                'snippet' => $snippet,
                            ];
                            $count++;
                        }
                    }
                }
                if ($breakChunk) return false;
            });
        return $suggestions;
    }

    private function extractLinkedSlugs($markdown)
    {
        // Cerca link markdown verso altri articoli magazine
        preg_match_all('/\[([^\]]+)\]\(\/magazine\/([a-z0-9\-]+)\)/i', $markdown, $matches);
        return $matches[2] ?? [];
    }

    private function sendMail($suggestions, $duration, $memory)
    {
        $to = env('MAGAZINE_ADMIN_EMAIL') ?: config('mail.admin_address', config('mail.from.address'));
        $count = count($suggestions);
        $body = "Trovati {$count} suggerimenti di link interni tra articoli magazine (score >= 80).\n\n";
        $body .= "Tempo di esecuzione: " . round($duration, 2) . " secondi. Memoria: {$memory}MB\n\n";
        foreach ($suggestions as $s) {
            $body .= "- Nell'articolo: [{$s['source']->title}](" . url('/magazine/' . $s['source']->slug) . ")\n";
            $body .= "  suggerisci di linkare: [{$s['target']->title}](" . url('/magazine/' . $s['target']->slug) . ") (score: {$s['score']})\n";
            $body .= "  → Frase: ..." . $s['snippet'] . "...\n";
        }
        Mail::raw($body, function ($m) use ($to, $count) {
            $m->to($to)
              ->subject("[Finanzamente] Suggerimenti link interni magazine ({$count})");
        });
    }
}
