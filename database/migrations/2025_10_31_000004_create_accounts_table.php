<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['bank', 'cash', 'card', 'broker', 'crypto', 'meal_voucher', 'pension_fund', 'other'])->default('bank');
            $table->decimal('initial_balance', 12, 2)->default(0);
            $table->string('currency_code');
            $table->boolean('active')->default(true);
            $table->boolean('is_private')->default(false);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency_code')->references('code')->on('currencies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
