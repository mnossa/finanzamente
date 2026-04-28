<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MagazineArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'slug',
        'title',
        'excerpt',
        'content',
        'cover_image_path',
        'cover_image_credit',
        'cover_image_credit_url',
        'author_name',
        'reading_time_minutes',
        'published_at',
        'is_featured',
        'is_ai_assisted',
        'views_count',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_ai_assisted' => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Solo articoli pubblicati e visibili al pubblico. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    // ── Relazioni ─────────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(MagazineCategory::class, 'category_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * URL dell'immagine di copertina.
     * Le immagini risiedono nel volume Docker "storage" → persistono tra i deploy.
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return asset('storage/'.$this->cover_image_path);
    }

    /**
     * Indica se l'articolo è una bozza (non ancora pubblicato).
     */
    public function getIsDraftAttribute(): bool
    {
        return $this->published_at === null || $this->published_at->isFuture();
    }

    /**
     * Contenuto Markdown convertito in HTML sicuro.
     */
    public function getContentHtmlAttribute(): string
    {
        return Str::markdownWithNofollow($this->content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Incrementa il contatore visite al massimo una volta per IP ogni 30 minuti.
     * Previene gonfiamento da bot/reload senza raccogliere dati personali
     * (la cache key usa hash SHA-256 dell'IP + slug, nessun dato in chiaro).
     */
    public function incrementViews(string $ipHash): void
    {
        $cacheKey = 'magazine_view:'.$this->slug.':'.$ipHash;

        if (! Cache::has($cacheKey)) {
            $this->increment('views_count');
            // TTL 30 minuti: una view per IP ogni mezz'ora
            Cache::put($cacheKey, 1, now()->addMinutes(30));
        }
    }

    /** Stima il tempo di lettura dal contenuto Markdown. */
    public static function estimateReadingTime(string $content): int
    {
        $wordsPerMinute = 200;
        $wordCount = str_word_count(strip_tags($content));

        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }
}
