<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magazine_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#4F46E5');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('magazine_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('magazine_categories')
                  ->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt');
            $table->longText('content'); // Markdown
            // Path relativo in storage/app/public  (es. magazine/covers/hero.webp)
            // Accessibile via asset('storage/magazine/covers/...')
            $table->string('cover_image_path')->nullable();
            $table->string('author_name')->default('Redazione Finanzamente');
            $table->unsignedSmallInteger('reading_time_minutes')->default(5);
            $table->timestamp('published_at')->nullable(); // null = bozza
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'id']);
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magazine_articles');
        Schema::dropIfExists('magazine_categories');
    }
};
