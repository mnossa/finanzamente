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
        Schema::table('transactions', function (Blueprint $table) {
            // Rimuovi il vincolo di foreign key esistente
            $table->dropForeign(['category_id']);

            // Modifica la colonna per renderla nullable
            $table->foreignId('category_id')->nullable()->change();

            // Ricrea il vincolo di foreign key
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Rimuovi il vincolo di foreign key
            $table->dropForeign(['category_id']);

            // Riporta la colonna a NOT NULL
            $table->foreignId('category_id')->nullable(false)->change();

            // Ricrea il vincolo di foreign key
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }
};
