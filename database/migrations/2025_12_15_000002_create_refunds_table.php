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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('UUID pubblico per riferimento esterno');
            $table->foreignId('original_transaction_id')->constrained('transactions')->cascadeOnDelete()->comment('Transazione originale di spesa');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Utente che ha creato il rimborso');
            $table->decimal('amount', 18, 8)->comment('Importo del rimborso (valore assoluto)');
            $table->string('currency_code');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed')->comment('Stato del rimborso');
            $table->text('description')->nullable()->comment('Descrizione del rimborso');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency_code')->references('code')->on('currencies');
        });

        // Aggiunge il campo refund_id alla tabella transactions
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('refund_id')->nullable()->after('transfer_id')->constrained('refunds')->nullOnDelete()->comment('Collegamento al rimborso (se presente)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['refund_id']);
            $table->dropColumn('refund_id');
        });

        Schema::dropIfExists('refunds');
    }
};
