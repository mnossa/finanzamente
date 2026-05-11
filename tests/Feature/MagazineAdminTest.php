<?php

namespace Tests\Feature;

use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MagazineAdminTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_EMAIL = 'owner@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        config(['prelaunch.magazine_admin_email' => self::OWNER_EMAIL]);
    }

    private function owner(): User
    {
        return User::factory()->create([
            'email' => self::OWNER_EMAIL,
            'email_verified_at' => now(),
        ]);
    }

    private function regularUser(): User
    {
        return User::factory()->create([
            'email' => 'utente@example.com',
            'email_verified_at' => now(),
        ]);
    }

    private function createCategory(): MagazineCategory
    {
        return MagazineCategory::create([
            'slug' => 'risparmio',
            'name' => 'Risparmio',
            'color' => '#10B981',
            'sort_order' => 1,
        ]);
    }

    private function createArticle(MagazineCategory $category, array $overrides = []): MagazineArticle
    {
        return MagazineArticle::create(array_merge([
            'category_id' => $category->id,
            'slug' => 'articolo-'.uniqid(),
            'title' => 'Articolo di test',
            'excerpt' => 'Un breve riassunto.',
            'content' => '## Titolo\n\nContenuto.',
            'author_name' => 'Redazione',
            'reading_time_minutes' => 1,
            'published_at' => now()->subDay(),
            'is_featured' => false,
            'views_count' => 0,
        ], $overrides));
    }

    // ── Accesso non autenticato ────────────────────────────────────────────────

    #[Test]
    public function admin_index_redirects_guests_to_login(): void
    {
        $this->get(route('admin.magazine.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_create_redirects_guests_to_login(): void
    {
        $this->get(route('admin.magazine.create'))
            ->assertRedirect(route('login'));
    }

    // ── Accesso utente non owner ───────────────────────────────────────────────

    #[Test]
    public function admin_index_returns_403_for_non_owner(): void
    {
        $this->actingAs($this->regularUser())
            ->get(route('admin.magazine.index'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_create_returns_403_for_non_owner(): void
    {
        $this->actingAs($this->regularUser())
            ->get(route('admin.magazine.create'))
            ->assertForbidden();
    }

    #[Test]
    public function admin_store_returns_403_for_non_owner(): void
    {
        $category = $this->createCategory();

        $this->actingAs($this->regularUser())
            ->post(route('admin.magazine.store'), [
                'title' => 'Titolo',
                'excerpt' => 'Riassunto',
                'content' => 'Contenuto',
                'category_id' => $category->id,
                'author_name' => 'Autore',
            ])
            ->assertForbidden();
    }

    // ── Accesso owner ─────────────────────────────────────────────────────────

    #[Test]
    public function admin_index_is_accessible_by_owner(): void
    {
        $this->actingAs($this->owner())
            ->get(route('admin.magazine.index'))
            ->assertOk();
    }

    #[Test]
    public function admin_create_is_accessible_by_owner(): void
    {
        $this->createCategory();

        $this->actingAs($this->owner())
            ->get(route('admin.magazine.create'))
            ->assertOk();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    #[Test]
    public function owner_can_create_published_article(): void
    {
        $category = $this->createCategory();

        $this->actingAs($this->owner())
            ->post(route('admin.magazine.store'), [
                'title' => 'Nuovo articolo',
                'excerpt' => 'Breve riassunto del nuovo articolo.',
                'content' => '## Intro\n\nContenuto del nuovo articolo.',
                'category_id' => $category->id,
                'author_name' => 'Redazione',
                'published_at' => now()->subHour()->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('admin.magazine.index'));

        $this->assertDatabaseHas('magazine_articles', ['title' => 'Nuovo articolo']);
    }

    #[Test]
    public function owner_can_create_draft_article(): void
    {
        $category = $this->createCategory();

        $this->actingAs($this->owner())
            ->post(route('admin.magazine.store'), [
                'title' => 'Bozza articolo',
                'excerpt' => 'Riassunto bozza.',
                'content' => 'Contenuto bozza.',
                'category_id' => $category->id,
                'author_name' => 'Redazione',
            ])
            ->assertRedirect(route('admin.magazine.index'));

        $this->assertDatabaseHas('magazine_articles', [
            'title' => 'Bozza articolo',
            'published_at' => null,
        ]);
    }

    #[Test]
    public function store_validates_required_fields(): void
    {
        $this->actingAs($this->owner())
            ->post(route('admin.magazine.store'), [])
            ->assertSessionHasErrors(['title', 'excerpt', 'content', 'category_id', 'author_name']);
    }

    #[Test]
    public function store_invalidates_magazine_nav_cache(): void
    {
        Cache::put('magazine_has_published', false, 3600);

        $category = $this->createCategory();

        $this->actingAs($this->owner())
            ->post(route('admin.magazine.store'), [
                'title' => 'Articolo cache',
                'excerpt' => 'Riassunto.',
                'content' => 'Contenuto.',
                'category_id' => $category->id,
                'author_name' => 'Redazione',
            ]);

        $this->assertFalse(Cache::has('magazine_has_published'));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    #[Test]
    public function owner_can_update_article(): void
    {
        $category = $this->createCategory();
        $article = $this->createArticle($category);

        $this->actingAs($this->owner())
            ->put(route('admin.magazine.update', $article), [
                'title' => 'Titolo modificato',
                'excerpt' => 'Riassunto aggiornato.',
                'content' => 'Contenuto aggiornato.',
                'category_id' => $category->id,
                'author_name' => 'Redazione',
            ])
            ->assertRedirect(route('admin.magazine.index'));

        $this->assertDatabaseHas('magazine_articles', ['title' => 'Titolo modificato']);
    }

    #[Test]
    public function non_owner_cannot_update_article(): void
    {
        $category = $this->createCategory();
        $article = $this->createArticle($category);

        $this->actingAs($this->regularUser())
            ->put(route('admin.magazine.update', $article), [
                'title' => 'Titolo modificato',
                'excerpt' => 'Riassunto.',
                'content' => 'Contenuto.',
                'category_id' => $category->id,
                'author_name' => 'Redazione',
            ])
            ->assertForbidden();
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    #[Test]
    public function owner_can_delete_article(): void
    {
        $category = $this->createCategory();
        $article = $this->createArticle($category);

        $this->actingAs($this->owner())
            ->delete(route('admin.magazine.destroy', $article))
            ->assertRedirect(route('admin.magazine.index'));

        $this->assertDatabaseMissing('magazine_articles', ['id' => $article->id]);
    }

    #[Test]
    public function non_owner_cannot_delete_article(): void
    {
        $category = $this->createCategory();
        $article = $this->createArticle($category);

        $this->actingAs($this->regularUser())
            ->delete(route('admin.magazine.destroy', $article))
            ->assertForbidden();

        $this->assertDatabaseHas('magazine_articles', ['id' => $article->id]);
    }

    #[Test]
    public function destroy_deletes_cover_image_if_present(): void
    {
        Storage::fake('public');

        $category = $this->createCategory();
        $article = $this->createArticle($category, [
            'cover_image_path' => 'magazine/covers/test.jpg',
        ]);
        Storage::disk('public')->put('magazine/covers/test.jpg', 'fake-image');

        $this->actingAs($this->owner())
            ->delete(route('admin.magazine.destroy', $article));

        Storage::disk('public')->assertMissing('magazine/covers/test.jpg');
    }
}
