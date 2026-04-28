<?php

namespace Tests\Unit;

use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MagazineArticleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(): MagazineCategory
    {
        return MagazineCategory::create([
            'slug' => 'risparmio',
            'name' => 'Risparmio',
            'color' => '#10B981',
            'sort_order' => 1,
        ]);
    }

    private function makeArticle(array $overrides = []): MagazineArticle
    {
        $category = $this->makeCategory();

        return MagazineArticle::create(array_merge([
            'category_id' => $category->id,
            'slug' => 'articolo-test',
            'title' => 'Articolo di test',
            'excerpt' => 'Un breve riassunto.',
            'content' => '## Titolo\n\nTesto del corpo dell\'articolo.',
            'author_name' => 'Redazione',
            'reading_time_minutes' => 1,
            'published_at' => now()->subDay(),
            'is_featured' => false,
            'views_count' => 0,
        ], $overrides));
    }

    // ── scopePublished ────────────────────────────────────────────────────────

    #[Test]
    public function published_scope_includes_past_articles(): void
    {
        $this->makeArticle(['published_at' => now()->subHour()]);

        $this->assertSame(1, MagazineArticle::published()->count());
    }

    #[Test]
    public function published_scope_excludes_drafts(): void
    {
        $this->makeArticle(['published_at' => null]);

        $this->assertSame(0, MagazineArticle::published()->count());
    }

    #[Test]
    public function published_scope_excludes_future_articles(): void
    {
        $this->makeArticle(['published_at' => now()->addDay()]);

        $this->assertSame(0, MagazineArticle::published()->count());
    }

    // ── getIsDraftAttribute ───────────────────────────────────────────────────

    #[Test]
    public function is_draft_is_true_when_published_at_is_null(): void
    {
        $article = $this->makeArticle(['published_at' => null]);

        $this->assertTrue($article->is_draft);
    }

    #[Test]
    public function is_draft_is_true_when_published_at_is_in_the_future(): void
    {
        $article = $this->makeArticle(['published_at' => now()->addDay()]);

        $this->assertTrue($article->is_draft);
    }

    #[Test]
    public function is_draft_is_false_when_published_at_is_in_the_past(): void
    {
        $article = $this->makeArticle(['published_at' => now()->subDay()]);

        $this->assertFalse($article->is_draft);
    }

    // ── getContentHtmlAttribute ───────────────────────────────────────────────

    #[Test]
    public function content_html_renders_markdown(): void
    {
        $article = $this->makeArticle(['content' => '**grassetto**']);

        $this->assertStringContainsString('<strong>grassetto</strong>', $article->content_html);
    }

    #[Test]
    public function content_html_strips_raw_html_tags(): void
    {
        // Il testo sicuro è in un paragrafo separato; il blocco <script> viene rimosso per intero.
        $article = $this->makeArticle(['content' => "testo sicuro\n\n<script>alert(1)</script>"]);

        $this->assertStringNotContainsString('<script>', $article->content_html);
        $this->assertStringContainsString('testo sicuro', $article->content_html);
    }

    // ── estimateReadingTime ───────────────────────────────────────────────────

    #[Test]
    public function estimate_reading_time_returns_at_least_one_minute(): void
    {
        $this->assertSame(1, MagazineArticle::estimateReadingTime('una parola'));
    }

    #[Test]
    public function estimate_reading_time_is_based_on_200_wpm(): void
    {
        $content = implode(' ', array_fill(0, 400, 'parola'));

        $this->assertSame(2, MagazineArticle::estimateReadingTime($content));
    }

    // ── incrementViews ────────────────────────────────────────────────────────

    #[Test]
    public function increment_views_increases_count_once(): void
    {
        $article = $this->makeArticle();

        $article->incrementViews('hash-ip-1');

        $this->assertSame(1, $article->fresh()->views_count);
    }

    #[Test]
    public function increment_views_does_not_count_same_ip_hash_twice(): void
    {
        $article = $this->makeArticle();

        $article->incrementViews('hash-ip-1');
        $article->incrementViews('hash-ip-1');

        $this->assertSame(1, $article->fresh()->views_count);
    }

    #[Test]
    public function increment_views_counts_different_ip_hashes_separately(): void
    {
        $article = $this->makeArticle();

        $article->incrementViews('hash-ip-1');
        $article->incrementViews('hash-ip-2');

        $this->assertSame(2, $article->fresh()->views_count);
    }

    #[Test]
    public function increment_views_counts_again_after_cache_expiry(): void
    {
        $article = $this->makeArticle();

        $article->incrementViews('hash-ip-1');
        Cache::flush();
        $article->incrementViews('hash-ip-1');

        $this->assertSame(2, $article->fresh()->views_count);
    }
}
