<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['static', 'formula'])->default('static');
            $table->decimal('static_value', 15, 2)->nullable();
            $table->text('formula_string')->nullable();
            $table->string('share_token')->nullable()->unique();
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('downloads_count')->default(0);
            $table->foreignId('source_id')->nullable()->constrained('financial_variables')->nullOnDelete();
            $table->boolean('is_official_template')->default(false);
            $table->string('template_slug', 64)->nullable()->unique();
            $table->timestamps();

            $table->unique(['user_id', 'code']);
            $table->index('share_token');
            $table->index('is_public');
            $table->index('is_official_template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_variables');
    }
};
