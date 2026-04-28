<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('debts_credits', function (Blueprint $table) {
            // Importo iniziale del debito/credito
            $table->decimal('initial_amount', 12, 2)->after('amount')->nullable();

            // Importo totale pagato
            $table->decimal('paid_amount', 12, 2)->default(0)->after('initial_amount');

            // Tasso di interesse annuale (percentuale)
            $table->decimal('interest_rate', 5, 2)->nullable()->after('paid_amount');

            // Tipo di interesse (semplice o composto)
            $table->enum('interest_type', ['simple', 'compound'])->default('simple')->after('interest_rate');

            // Data di riferimento per il calcolo degli interessi
            $table->date('interest_calculation_date')->nullable()->after('interest_type');
        });

        // Popola initial_amount con il valore di amount per i record esistenti
        DB::table('debts_credits')->update(['initial_amount' => DB::raw('amount')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debts_credits', function (Blueprint $table) {
            $table->dropColumn([
                'initial_amount',
                'paid_amount',
                'interest_rate',
                'interest_type',
                'interest_calculation_date',
            ]);
        });
    }
};
