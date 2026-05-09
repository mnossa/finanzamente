<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge tracking multi-currency alle transazioni.
     *
     * - `exchange_rate_to_base` + `amount_base`: snapshot del tasso di cambio
     *   verso la valuta base (EUR) al momento del salvataggio. Servono per
     *   aggregazioni cross-conto/cross-valuta nelle dashboard.
     * - `original_amount` + `original_currency_code`: opzionali. Si usano quando
     *   l'utente ha pagato in una valuta diversa da quella del conto (es. £30
     *   con carta IT in EUR che ha addebitato €35.40). `amount` resta in valuta
     *   del conto per coerenza con l'estratto bancario.
     *
     * Backfill: tutte le transazioni esistenti sono di fatto in EUR (il sistema
     * forzava EUR ovunque), quindi `exchange_rate_to_base = 1` e `amount_base = amount`.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('exchange_rate_to_base', 20, 10)->default(1)->after('currency_code');
            $table->decimal('amount_base', 12, 2)->default(0)->after('exchange_rate_to_base');
            $table->decimal('original_amount', 12, 2)->nullable()->after('amount_base');
            $table->string('original_currency_code', 3)->nullable()->after('original_amount');

            $table->foreign('original_currency_code')->references('code')->on('currencies');
            $table->index('amount_base');
        });

        DB::table('transactions')->update([
            'exchange_rate_to_base' => 1,
            'amount_base' => DB::raw('amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['original_currency_code']);
            $table->dropIndex(['amount_base']);
            $table->dropColumn([
                'exchange_rate_to_base',
                'amount_base',
                'original_amount',
                'original_currency_code',
            ]);
        });
    }
};
