<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SuggestArticleLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggests_links_between_articles(): void
    {
        Mail::fake();

        $category = MagazineCategory::factory()->create();

        $articles = MagazineArticle::factory()->count(3)->create([
            'category_id' => $category->id,
            'published_at' => now()->subDay(),
            'content' => str_repeat('Questo articolo parla di risparmio energetico e investimenti personali. ', 10),
        ]);

        // Simula risposta del servizio python-linker
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
}
