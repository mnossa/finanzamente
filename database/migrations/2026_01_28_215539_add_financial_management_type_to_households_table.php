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
        Schema::table('households', function (Blueprint $table) {
            $table->enum('financial_management_type', ['debt_balancing', 'shared_wallet'])
                ->default('shared_wallet')
                ->comment('Modalità di gestione finanziaria: debt_balancing = Bilanciamento Debiti, shared_wallet = Portafoglio Comune');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('financial_management_type');
        });
    }
};
