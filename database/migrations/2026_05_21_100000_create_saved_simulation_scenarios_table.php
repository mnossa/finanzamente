<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_simulation_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('tab', 32);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['household_id', 'user_id', 'name']);
            $table->index(['household_id', 'tab']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_simulation_scenarios');
    }
};
