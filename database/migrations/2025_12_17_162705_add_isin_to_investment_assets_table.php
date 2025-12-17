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
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->string('isin', 12)->nullable()->after('symbol');
            $table->string('exchange', 50)->nullable()->after('isin');
            
            // Indice per ricerca ISIN
            $table->index('isin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->dropIndex(['isin']);
            $table->dropColumn(['isin', 'exchange']);
        });
    }
};
