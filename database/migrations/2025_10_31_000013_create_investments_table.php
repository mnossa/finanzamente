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
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('asset_id')->constrained('investment_assets')->cascadeOnDelete();
            $table->decimal('quantity', 18, 8);
            $table->decimal('buy_price', 18, 8);
            $table->date('buy_date');
            $table->decimal('sell_price', 18, 8)->nullable();
            $table->date('sell_date')->nullable();
            $table->decimal('fees', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
