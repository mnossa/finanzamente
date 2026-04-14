<?php

namespace Tests\Feature;

use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MagazinePublicTest extends TestCase
{
    use RefreshDatabase;

    private function createCategory(array $overrides = []): MagazineCategory
    {
        return MagazineCategory::create(array_merge([
            'slug'       => 'risparmio-' . uniqid(),
            'name'       => 'Risparmio',
            'color'      => '#10B981',
            'sort_order' => 1,
        ], $overrides));
    }

    private function createArticle(MagazineCategory $category, array $overrides = []): MagazineArticle
    {
        return MagazineArticle::create(array_merge([
            'category_id'          => $category->id,
            'slug'                 => 'articolo-' . uniqid(),
            'title'                => 'Articolo di esempio',
            'excerpt'              => 'Un breve riassunto del contenuto.',
            'content'              => '## Intro\n\nContenuto dell\'articolo.',
            'author_name'          => 'Redazione',
            'reading_time_minutes' => 2,
            'published_at'         => now()->subDay(),
            'is_featured'          => false,
            'views_count'          => 0,
        ], $overrides));
    }

    // ── Magazine index ────────────────────────────────────────────────────────

    #[Test]
    public function magazine_index_returns_200(): void
    {
        $this->get(route('magazine.index'))->assertOk();
    }

    #[Test]
    public function magazine_index_shows_published_articles(): void
    {
        $category = $this->createCategory();
        $article  = $this->createArticle($category, ['title' => 'Articolo visibile']);

        $this->get(route('magazine.index'))
            ->assertOk()
            ->assertSee('Articolo visibile');
    }

    #[Test]
    public function magazine_index_does_not_show_drafts(): void
    {
        $category = $this->createCategory();
        $this->createArticle($category, [
            'title'        => 'Articolo nascosto',
            'published_at' => null,
        ]);

        $this->get(route('magazine.index'))
            ->assertOk()
            ->assertDontSee('Articolo nascosto');
    }

    #[Test]
    public function magazine_index_does_not_show_future_articles(): void
    {
        $category = $this->createCategory();
        $this->createArticle($category, [
            'title'        => 'Articolo futuro',
            'published_at' => now()->addWeek(),
        ]);

        $this->get(route('magazine.index'))
            ->assertOk()
            ->assertDontSee('Articolo futuro');
    }

    // ── Magazine category ─────────────────────────────────────────────────────

    #[Test]
    public function magazine_category_returns_200_for_existing_category(): void
    {
        $category = $this->createCategory(['slug' => 'risparmio']);

        $this->get(route('magazine.category', 'risparmio'))->assertOk();
    }

    #[Test]
    public function magazine_category_returns_404_for_unknown_slug(): void
    {
        $this->get(route('magazine.category', 'inesistente'))->assertNotFound();
    }

    #[Test]
    public function magazine_category_shows_only_articles_of_that_category(): void
    {
        $cat1 = $this->createCategory(['slug' => 'cat-uno', 'name' => 'Cat Uno']);
        $cat2 = $this->createCategory(['slug' => 'cat-due', 'name' => 'Cat Due']);

        $this->createArticle($cat1, ['title' => 'Articolo categoria uno']);
        $this->createArticle($cat2, ['title' => 'Articolo categoria due']);

        $this->get(route('magazine.category', 'cat-uno'))
            ->assertOk()
            ->assertSee('Articolo categoria uno')
            ->assertDontSee('Articolo categoria due');
    }

    // ── Magazine show ─────────────────────────────────────────────────────────

    #[Test]
    public function magazine_show_returns_200_for_published_article(): void
    {
        $category = $this->createCategory();
        $article  = $this->createArticle($category, ['slug' => 'articolo-slug']);

        $this->get(route('magazine.show', 'articolo-slug'))->assertOk();
    }

    #[Test]
    public function magazine_show_returns_404_for_unknown_slug(): void
    {
        $this->get(route('magazine.show', 'non-esiste'))->assertNotFound();
    }

    #[Test]
    public function magazine_show_returns_404_for_draft(): void
    {
        $category = $this->createCategory();
        $this->createArticle($category, [
            'slug'         => 'bozza-slug',
            'published_at' => null,
        ]);

        $this->get(route('magazine.show', 'bozza-slug'))->assertNotFound();
    }

    #[Test]
    public function magazine_show_displays_article_title_and_content(): void
    {
        $category = $this->createCategory();
        $article  = $this->createArticle($category, [
            'slug'    => 'articolo-visibile',
            'title'   => 'Titolo articolo test',
            'content' => 'Contenuto di prova',
        ]);

        $this->get(route('magazine.show', 'articolo-visibile'))
            ->assertOk()
            ->assertSee('Titolo articolo test')
            ->assertSee('Contenuto di prova');
    }

    #[Test]
    public function magazine_show_increments_views_count(): void
    {
        $category = $this->createCategory();
        $article  = $this->createArticle($category, ['slug' => 'articolo-views']);

        $this->get(route('magazine.show', 'articolo-views'));

        $this->assertSame(1, $article->fresh()->views_count);
    }

    #[Test]
    public function magazine_show_does_not_double_count_same_ip_within_30_minutes(): void
    {
        $category = $this->createCategory();
        $article  = $this->createArticle($category, ['slug' => 'articolo-dedup']);

        $this->get(route('magazine.show', 'articolo-dedup'));
        $this->get(route('magazine.show', 'articolo-dedup'));

        $this->assertSame(1, $article->fresh()->views_count);
    }

    // ── Nav link visibility ───────────────────────────────────────────────────

    #[Test]
    public function nav_magazine_link_is_hidden_when_no_published_articles(): void
    {
        Cache::forget('magazine_has_published');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('href="' . route('magazine.index') . '"', false);
    }

    #[Test]
    public function nav_magazine_link_is_visible_when_published_articles_exist(): void
    {
        Cache::forget('magazine_has_published');

        $category = $this->createCategory();
        $this->createArticle($category);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('magazine.index'), false);
    }

    // ── UTM tracking su CTA ───────────────────────────────────────────────────

    #[Test]
    public function article_cta_contains_utm_parameters(): void
    {
        $category = $this->createCategory();
        $article  = $this->createArticle($category, ['slug' => 'articolo-utm']);

        $this->get(route('magazine.show', 'articolo-utm'))
            ->assertOk()
            ->assertSee('utm_source=magazine', false)
            ->assertSee('utm_medium=article_cta', false)
            ->assertSee('utm_campaign=articolo-utm', false);
    }
}
