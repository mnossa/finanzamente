<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache giornaliera dei tassi di cambio.
     *
     * Una riga per ciascuna coppia (base_code, quote_code, date): "1 base = rate quote".
     * Lo storico è importante: un tasso storico non deve cambiare mai più (audit contabile).
     * `source` traccia da dove arriva il dato: `frankfurter`, `manual`, `fallback`.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_code', 3);
            $table->string('quote_code', 3);
            $table->date('date');
            $table->decimal('rate', 20, 10);
            $table->string('source', 20)->default('frankfurter');
            $table->timestamps();

            $table->unique(['base_code', 'quote_code', 'date']);
            $table->foreign('base_code')->references('code')->on('currencies');
            $table->foreign('quote_code')->references('code')->on('currencies');
            $table->index(['quote_code', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
