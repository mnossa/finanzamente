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
        Schema::create('investment_assets', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['crypto', 'etf', 'stock', 'index', 'commodity', 'insurance', 'other'])->default('other');
            $table->string('symbol')->nullable();
            $table->string('name');
            $table->string('currency_code');
            $table->json('extra_data')->nullable();
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
        Schema::dropIfExists('investment_assets');
    }
};
