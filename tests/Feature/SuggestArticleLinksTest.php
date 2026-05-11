<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LinkSuggestion;
use App\Models\LinkSuggestionRun;
use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SuggestArticleLinksTest extends TestCase
{
    use RefreshDatabase;

    private MagazineCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = MagazineCategory::factory()->create();
    }

    public function test_suggests_links_between_articles(): void
    {
        Mail::fake();

        $articles = MagazineArticle::factory()->count(3)->create([
            'category_id' => $this->category->id,
            'published_at' => now()->subDay(),
            'content' => str_repeat('Questo articolo parla di risparmio energetico e investimenti personali. ', 10),
        ]);

        Http::fake([
            '*/health' => Http::response(['status' => 'ok', 'model' => 'test'], 200),
            '*/batch-suggest' => Http::response([
                'suggestions' => [
                    [
                        'source_id' => $articles[0]->id,
                        'target_id' => $articles[1]->id,
                        'target_slug' => $articles[1]->slug,
                        'target_title' => $articles[1]->title,
                        'score' => 0.85,
                        'snippet' => 'Questo articolo parla di risparmio energetico.',
                    ],
                ],
                'articles_processed' => 3,
            ], 200),
        ]);

        $this->artisan('magazine:link-suggestions', ['--max' => 10, '--per-article' => 2])
            ->expectsOutputToContain('Suggerimenti generati: 1')
            ->assertExitCode(0);
    }

    public function test_exits_with_error_when_linker_unavailable(): void
    {
        Mail::fake();

        Http::fake([
            '*/health' => Http::response('', 503),
        ]);

        $this->artisan('magazine:link-suggestions')
            ->assertExitCode(1);
    }

    public function test_saves_run_and_suggestions_to_history(): void
    {
        Mail::fake();

        $articles = MagazineArticle::factory()->count(3)->create([
            'category_id' => $this->category->id,
            'published_at' => now()->subDay(),
            'content' => str_repeat('Investimenti e risparmio sono fondamentali per la libertà finanziaria. ', 10),
        ]);

        Http::fake([
            '*/health' => Http::response(['status' => 'ok', 'model' => 'test'], 200),
            '*/batch-suggest' => Http::response([
                'suggestions' => [
                    [
                        'source_id' => $articles[0]->id,
                        'target_id' => $articles[1]->id,
                        'target_slug' => $articles[1]->slug,
                        'target_title' => $articles[1]->title,
                        'score' => 0.78,
                        'snippet' => 'Investimenti e risparmio.',
                    ],
                ],
                'articles_processed' => 3,
            ], 200),
        ]);

        $this->artisan('magazine:link-suggestions')->assertExitCode(0);

        $this->assertDatabaseCount('link_suggestion_runs', 1);
        $this->assertDatabaseCount('link_suggestions', 1);

        $run = LinkSuggestionRun::first();
        $this->assertEquals(3, $run->articles_processed);
        $this->assertEquals(1, $run->suggestions_count);
        $this->assertEquals(0, $run->implemented_count);

        $suggestion = LinkSuggestion::first();
        $this->assertEquals($articles[0]->id, $suggestion->source_article_id);
        $this->assertEquals($articles[1]->id, $suggestion->target_article_id);
        $this->assertEquals('pending', $suggestion->status);
        $this->assertEqualsWithDelta(0.78, $suggestion->score, 0.001);
    }

    public function test_pending_suggestions_are_excluded_from_new_run(): void
    {
        Mail::fake();

        $articles = MagazineArticle::factory()->count(3)->create([
            'category_id' => $this->category->id,
            'published_at' => now()->subDay(),
            'content' => str_repeat('Budget mensile e gestione delle spese fisse quotidiane. ', 10),
        ]);

        // Crea un run precedente con un suggerimento pending
        $existingRun = LinkSuggestionRun::create([
            'ran_at' => now()->subWeek(),
            'articles_processed' => 3,
            'suggestions_count' => 1,
            'implemented_count' => 0,
            'duration_seconds' => 1.5,
        ]);
        LinkSuggestion::create([
            'run_id' => $existingRun->id,
            'source_article_id' => $articles[0]->id,
            'target_article_id' => $articles[1]->id,
            'score' => 0.75,
            'snippet' => 'Budget mensile.',
            'status' => 'pending',
        ]);

        $capturedAlreadyLinked = null;
        Http::fake([
            '*/health' => Http::response(['status' => 'ok', 'model' => 'test'], 200),
            '*/batch-suggest' => function ($request) use (&$capturedAlreadyLinked) {
                $capturedAlreadyLinked = $request->data()['already_linked'] ?? [];

                return Http::response([
                    'suggestions' => [],
                    'articles_processed' => 3,
                ], 200);
            },
        ]);

        $this->artisan('magazine:link-suggestions')->assertExitCode(0);

        // Il target del suggerimento pending deve essere nella mappa di esclusione
        $sourceKey = (string) $articles[0]->id;
        $this->assertArrayHasKey($sourceKey, $capturedAlreadyLinked);
        $this->assertContains($articles[1]->slug, $capturedAlreadyLinked[$sourceKey]);
    }

    public function test_auto_detects_implemented_suggestions(): void
    {
        Mail::fake();

        $targetSlug = 'guida-risparmio';
        $sourceArticle = MagazineArticle::factory()->create([
            'category_id' => $this->category->id,
            'published_at' => now()->subDay(),
            // Il contenuto ora include già il link al target
            'content' => str_repeat('Risparmio e investimenti. ', 10)
                ." Vedi [guida completa](/magazine/{$targetSlug}) per dettagli.",
        ]);
        $targetArticle = MagazineArticle::factory()->create([
            'category_id' => $this->category->id,
            'published_at' => now()->subDay(),
            'slug' => $targetSlug,
            'content' => str_repeat('Guida al risparmio personale e gestione patrimonio. ', 10),
        ]);

        // Suggerimento pending creato in un run precedente
        $existingRun = LinkSuggestionRun::create([
            'ran_at' => now()->subWeek(),
            'articles_processed' => 2,
            'suggestions_count' => 1,
            'implemented_count' => 0,
            'duration_seconds' => 1.0,
        ]);
        $pendingSuggestion = LinkSuggestion::create([
            'run_id' => $existingRun->id,
            'source_article_id' => $sourceArticle->id,
            'target_article_id' => $targetArticle->id,
            'score' => 0.80,
            'snippet' => 'Risparmio e investimenti.',
            'status' => 'pending',
        ]);

        Http::fake([
            '*/health' => Http::response(['status' => 'ok', 'model' => 'test'], 200),
            '*/batch-suggest' => Http::response([
                'suggestions' => [],
                'articles_processed' => 2,
            ], 200),
        ]);

        $this->artisan('magazine:link-suggestions')
            ->expectsOutputToContain('Suggerimenti implementati rilevati in questo run: 1')
            ->assertExitCode(0);

        $pendingSuggestion->refresh();
        $this->assertEquals('implemented', $pendingSuggestion->status);
        $this->assertNotNull($pendingSuggestion->implemented_at);

        // Il run creato in questa sessione deve registrare 1 implementato
        $latestRun = LinkSuggestionRun::orderBy('id', 'desc')->first();
        $this->assertEquals(1, $latestRun->implemented_count);
    }

    public function test_duplicate_pairs_from_python_are_deduplicated(): void
    {
        Mail::fake();

        $articles = MagazineArticle::factory()->count(3)->create([
            'category_id' => $this->category->id,
            'published_at' => now()->subDay(),
            'content' => str_repeat('Finanza personale e pianificazione del patrimonio familiare. ', 10),
        ]);

        // Python restituisce la stessa coppia due volte (edge case difensivo)
        Http::fake([
            '*/health' => Http::response(['status' => 'ok', 'model' => 'test'], 200),
            '*/batch-suggest' => Http::response([
                'suggestions' => [
                    [
                        'source_id' => $articles[0]->id,
                        'target_id' => $articles[1]->id,
                        'target_slug' => $articles[1]->slug,
                        'target_title' => $articles[1]->title,
                        'score' => 0.82,
                        'snippet' => 'Finanza personale.',
                    ],
                    [
                        'source_id' => $articles[0]->id,
                        'target_id' => $articles[1]->id,
                        'target_slug' => $articles[1]->slug,
                        'target_title' => $articles[1]->title,
                        'score' => 0.82,
                        'snippet' => 'Finanza personale duplicato.',
                    ],
                ],
                'articles_processed' => 3,
            ], 200),
        ]);

        $this->artisan('magazine:link-suggestions')
            ->expectsOutputToContain('Suggerimenti generati: 1')
            ->assertExitCode(0);

        // Nonostante Python abbia restituito la coppia due volte, deve essere salvata una sola volta
        $this->assertDatabaseCount('link_suggestions', 1);
    }

    public function test_already_linked_articles_are_not_suggested(): void
    {
        Mail::fake();

        $targetSlug = 'articolo-gia-linkato';
        $sourceArticle = MagazineArticle::factory()->create([
            'category_id' => $this->category->id,
            'published_at' => now()->subDay(),
            'content' => str_repeat('Investimenti e pianificazione finanziaria. ', 10)
                ." Leggi [questo](/magazine/{$targetSlug}) per saperne di più.",
        ]);
        $targetArticle = MagazineArticle::factory()->create([
            'category_id' => $this->category->id,
            'published_at' => now()->subDay(),
            'slug' => $targetSlug,
            'content' => str_repeat('Pianificazione e obiettivi finanziari a lungo termine. ', 10),
        ]);

        // Python restituisce un suggerimento per un target già presente nel contenuto
        Http::fake([
            '*/health' => Http::response(['status' => 'ok', 'model' => 'test'], 200),
            '*/batch-suggest' => Http::response([
                'suggestions' => [
                    [
                        'source_id' => $sourceArticle->id,
                        'target_id' => $targetArticle->id,
                        'target_slug' => $targetArticle->slug,
                        'target_title' => $targetArticle->title,
                        'score' => 0.79,
                        'snippet' => 'Investimenti.',
                    ],
                ],
                'articles_processed' => 2,
            ], 200),
        ]);

        $this->artisan('magazine:link-suggestions')
            ->expectsOutputToContain('Suggerimenti generati: 0')
            ->assertExitCode(0);

        // Nessun suggerimento deve essere salvato perché il link è già nel contenuto
        $this->assertDatabaseCount('link_suggestions', 0);
    }
}
