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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('source_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('destination_account_id')->constrained('accounts')->cascadeOnDelete();

            $table->decimal('source_amount', 18, 8);
            $table->string('source_currency', 10);
            $table->decimal('dest_amount', 18, 8);
            $table->string('dest_currency', 10);

            $table->decimal('exchange_rate', 28, 12)->nullable();
            $table->decimal('fee', 18, 8)->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['completed', 'cancelled'])->default('completed');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
