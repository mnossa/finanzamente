<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\LinkSuggestion;
use App\Models\LinkSuggestionRun;
use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il comportamento dei modelli LinkSuggestion e LinkSuggestionRun.
 */
class LinkSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private function makeRun(): LinkSuggestionRun
    {
        return LinkSuggestionRun::create([
            'ran_at' => now(),
            'articles_processed' => 5,
            'suggestions_count' => 2,
            'implemented_count' => 0,
            'duration_seconds' => 1.23,
        ]);
    }

    private function makeSuggestion(LinkSuggestionRun $run, array $overrides = []): LinkSuggestion
    {
        $category = MagazineCategory::factory()->create();
        $source = MagazineArticle::factory()->create(['category_id' => $category->id, 'published_at' => now()]);
        $target = MagazineArticle::factory()->create(['category_id' => $category->id, 'published_at' => now()]);

        return LinkSuggestion::create(array_merge([
            'run_id' => $run->id,
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'score' => 0.75,
            'snippet' => 'Frammento di testo sorgente.',
            'status' => 'pending',
        ], $overrides));
    }

    public function test_mark_implemented_sets_status_and_timestamp(): void
    {
        $run = $this->makeRun();
        $suggestion = $this->makeSuggestion($run);

        $this->assertEquals('pending', $suggestion->status);
        $this->assertNull($suggestion->implemented_at);

        $suggestion->markImplemented();
        $suggestion->refresh();

        $this->assertEquals('implemented', $suggestion->status);
        $this->assertNotNull($suggestion->implemented_at);
    }

    public function test_mark_dismissed_sets_status_and_timestamp(): void
    {
        $run = $this->makeRun();
        $suggestion = $this->makeSuggestion($run);

        $suggestion->markDismissed();
        $suggestion->refresh();

        $this->assertEquals('dismissed', $suggestion->status);
        $this->assertNotNull($suggestion->dismissed_at);
    }

    public function test_pending_scope_filters_correctly(): void
    {
        $run = $this->makeRun();
        $this->makeSuggestion($run, ['status' => 'pending']);
        $this->makeSuggestion($run, ['status' => 'implemented', 'implemented_at' => now()]);
        $this->makeSuggestion($run, ['status' => 'dismissed', 'dismissed_at' => now()]);

        $this->assertCount(1, LinkSuggestion::pending()->get());
    }

    public function test_implemented_scope_filters_correctly(): void
    {
        $run = $this->makeRun();
        $this->makeSuggestion($run, ['status' => 'pending']);
        $this->makeSuggestion($run, ['status' => 'implemented', 'implemented_at' => now()]);

        $this->assertCount(1, LinkSuggestion::implemented()->get());
    }

    public function test_run_has_many_suggestions(): void
    {
        $run = $this->makeRun();
        $this->makeSuggestion($run);
        $this->makeSuggestion($run);

        $this->assertCount(2, $run->suggestions);
    }

    public function test_suggestion_belongs_to_run(): void
    {
        $run = $this->makeRun();
        $suggestion = $this->makeSuggestion($run);

        $this->assertEquals($run->id, $suggestion->run->id);
    }

    public function test_suggestion_belongs_to_source_and_target_articles(): void
    {
        $run = $this->makeRun();
        $suggestion = $this->makeSuggestion($run);

        $this->assertInstanceOf(MagazineArticle::class, $suggestion->sourceArticle);
        $this->assertInstanceOf(MagazineArticle::class, $suggestion->targetArticle);
        $this->assertNotEquals($suggestion->source_article_id, $suggestion->target_article_id);
    }
}
