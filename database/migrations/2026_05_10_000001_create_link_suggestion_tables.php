<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recover from partial run (first table created, then migration failed before completion).
        Schema::dropIfExists('link_suggestions');
        Schema::dropIfExists('link_suggestion_runs');

        Schema::create('link_suggestion_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('ran_at');
            $table->unsignedInteger('articles_processed')->default(0);
            $table->unsignedInteger('suggestions_count')->default(0);
            $table->unsignedInteger('implemented_count')->default(0);
            $table->float('duration_seconds')->default(0);
            $table->timestamps();
        });

        Schema::create('link_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')
                ->constrained('link_suggestion_runs')
                ->cascadeOnDelete();
            $table->foreignId('source_article_id')
                ->constrained('magazine_articles')
                ->cascadeOnDelete();
            $table->foreignId('target_article_id')
                ->constrained('magazine_articles')
                ->cascadeOnDelete();
            $table->float('score');
            $table->text('snippet')->nullable();
            $table->enum('status', ['pending', 'implemented', 'dismissed'])->default('pending');
            $table->timestamp('implemented_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['source_article_id', 'target_article_id', 'status'],
                'lnk_sugg_src_tgt_stat_idx'
            );
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_suggestions');
        Schema::dropIfExists('link_suggestion_runs');
    }
};
