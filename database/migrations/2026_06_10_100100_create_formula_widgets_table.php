<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formula_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_variable_id')->constrained('financial_variables')->cascadeOnDelete();
            $table->string('name', 120);
            $table->enum('display_type', ['kpi', 'line', 'area', 'bar', 'stacked_bar', 'progress'])->default('kpi');
            $table->string('period_preset', 32)->nullable();
            $table->json('chart_config')->nullable();
            $table->enum('default_size', ['sm', 'md', 'lg', 'xl'])->default('md');
            $table->string('share_token')->nullable()->unique();
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('downloads_count')->default(0);
            $table->foreignId('source_id')->nullable()->constrained('formula_widgets')->nullOnDelete();
            $table->boolean('is_official_template')->default(false);
            $table->string('template_slug', 64)->nullable()->unique();
            $table->timestamps();

            $table->index('share_token');
            $table->index('is_public');
            $table->index('is_official_template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_widgets');
    }
};
