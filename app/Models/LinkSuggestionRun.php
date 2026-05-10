<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkSuggestionRun extends Model
{
    protected $fillable = [
        'ran_at',
        'articles_processed',
        'suggestions_count',
        'implemented_count',
        'duration_seconds',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
        'duration_seconds' => 'float',
    ];

    public function suggestions(): HasMany
    {
        return $this->hasMany(LinkSuggestion::class, 'run_id');
    }
}
