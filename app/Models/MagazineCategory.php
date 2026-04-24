<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MagazineCategory extends Model
{
    use HasFactory;
    protected $fillable = [
        'slug',
        'name',
        'description',
        'color',
        'sort_order',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(MagazineArticle::class, 'category_id');
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(MagazineArticle::class, 'category_id')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }
}
