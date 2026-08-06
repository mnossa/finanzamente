<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            // Stato: draft | needs_review | confirmed | rejected
            $table->string('status', 30)->default('draft');
            // Sorgente di creazione: telegram_text | telegram_photo | manual
            $table->string('source', 30)->default('manual');
            // Testo originale inviato dall'utente
            $table->text('raw_text')->nullable();
            // Path immagine relativo al disco 'private'
            $table->string('image_path')->nullable();
            // Dati estratti dall'AI (schema ottimizzato: amt, shop, dt)
            $table->json('ai_payload')->nullable();
            // Dati confermati/modificati dall'utente
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('description')->nullable();
            $table->date('transaction_date')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            // Collegamento alla transazione creata al momento della conferma
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_items');
    }
};
