<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkSuggestion extends Model
{
    protected $fillable = [
        'run_id',
        'source_article_id',
        'target_article_id',
        'score',
        'snippet',
        'status',
        'implemented_at',
        'dismissed_at',
    ];

    protected $casts = [
        'score' => 'float',
        'implemented_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(LinkSuggestionRun::class, 'run_id');
    }

    public function sourceArticle(): BelongsTo
    {
        return $this->belongsTo(MagazineArticle::class, 'source_article_id');
    }

    public function targetArticle(): BelongsTo
    {
        return $this->belongsTo(MagazineArticle::class, 'target_article_id');
    }

    public function markImplemented(): void
    {
        $this->update(['status' => 'implemented', 'implemented_at' => now()]);
    }

    public function markDismissed(): void
    {
        $this->update(['status' => 'dismissed', 'dismissed_at' => now()]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeImplemented($query)
    {
        return $query->where('status', 'implemented');
    }
}
