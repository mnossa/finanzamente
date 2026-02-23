<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('template_type'); // fotovoltaico, auto_elettrica, cappotto_termico, pompa_calore, personalizzato
            $table->date('start_date')->nullable();
            $table->decimal('initial_cost', 12, 2)->default(0);
            $table->json('recurring_costs')->nullable();
            $table->json('savings')->nullable();
            $table->json('incentives')->nullable();
            $table->json('template_data')->nullable();
            $table->decimal('total_annual_savings', 12, 2)->nullable();
            $table->decimal('breakeven_years', 8, 2)->nullable();
            $table->decimal('roi_percentage', 8, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_analyses');
    }
};
