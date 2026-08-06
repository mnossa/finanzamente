<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_transaction_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency_code');
            $table->string('description')->nullable();
            $table->enum('detected_frequency', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->decimal('confidence', 3, 2)->default(0.00);
            $table->enum('status', ['pending', 'accepted', 'ignored'])->default('pending');
            $table->json('transaction_ids');
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transaction_suggestions');
    }
};
