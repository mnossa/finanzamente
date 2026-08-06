<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_credit_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_credit_id')->constrained('debts_credits')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('kind', 50)->default('non_monetary_reduction');
            $table->date('effective_date');
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_credit_adjustments');
    }
};
