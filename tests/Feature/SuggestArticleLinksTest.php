<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MagazineArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuggestArticleLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggests_links_between_articles()
    {
        $a = MagazineArticle::factory()->create([
            'title' => 'Risparmio energetico',
            'content' => 'Questo articolo parla di risparmio energetico e investimenti.'
        ]);
        $b = MagazineArticle::factory()->create([
            'title' => 'Investimenti',
            'content' => 'Guida agli investimenti.'
        ]);
        $c = MagazineArticle::factory()->create([
            'title' => 'Budgeting',
            'content' => 'Come fare budgeting.'
        ]);

        $this->artisan('magazine:link-suggestions', ['--max' => 10, '--per-article' => 2])
            ->expectsOutputToContain('Suggerimenti generati:')
            ->assertExitCode(0);
    }
}
