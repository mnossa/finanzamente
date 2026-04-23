<?php

namespace Database\Seeders;

use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use Illuminate\Database\Seeder;

class MagazineArticleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $categories = MagazineCategory::all();
        $count = 0;
        foreach ($categories as $category) {
            $n = rand(5, 6);
            MagazineArticle::factory()->count($n)->create([
                'category_id' => $category->id,
            ]);
            $count += $n;
        }
        $this->command->info("Articoli demo magazine creati: $count");
    }
}
