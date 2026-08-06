<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_pacs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('investment_asset_id')->constrained('investment_assets')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 3);
            $table->string('frequency', 20)->default('monthly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('last_executed_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_pacs');
    }
};
