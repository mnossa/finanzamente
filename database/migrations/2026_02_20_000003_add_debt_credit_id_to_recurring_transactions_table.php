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
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->foreignId('debt_credit_id')
                ->nullable()
                ->after('last_generated_date')
                ->constrained('debts_credits')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropForeign(['debt_credit_id']);
            $table->dropColumn('debt_credit_id');
        });
    }
};
