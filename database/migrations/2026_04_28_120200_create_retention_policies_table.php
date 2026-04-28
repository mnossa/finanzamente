<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_key')->unique();
            $table->string('description');
            $table->unsignedInteger('retention_days');
            $table->unsignedInteger('anonymize_after_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('version');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_policies');
    }
};
