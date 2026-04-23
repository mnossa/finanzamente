<?php

namespace Database\Factories;

use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MagazineArticleFactory extends Factory
{
    protected $model = MagazineArticle::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(4);
        $slug = Str::slug($title) . '-' . $this->faker->unique()->word();
        return [
            'category_id' => MagazineCategory::inRandomOrder()->first()?->id ?? 1,
            'slug' => $slug,
            'title' => $title,
            'excerpt' => $this->faker->sentence(10),
            'content' => $this->faker->paragraphs(5, true),
            'cover_image_path' => 'https://placehold.co/800x400/png?text=Magazine',
            'cover_image_credit' => 'Placeholder',
            'cover_image_credit_url' => 'https://placehold.co/',
            'author_name' => $this->faker->name(),
            'reading_time_minutes' => $this->faker->numberBetween(3, 10),
            'published_at' => now()->subDays($this->faker->numberBetween(1, 30)),
            'is_featured' => $this->faker->boolean(20),
            'is_ai_assisted' => $this->faker->boolean(10),
            'views_count' => $this->faker->numberBetween(0, 500),
            'meta_title' => $title,
            'meta_description' => $this->faker->sentence(12),
        ];
    }
}
