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
        Schema::table('magazine_articles', function (Blueprint $table) {
            // Attribution dell'immagine di copertina (es. "Photo by John Doe on Unsplash")
            $table->string('cover_image_credit')->nullable()->after('cover_image_path');
            // URL della pagina Unsplash dell'autore (per il link nell'attribution)
            $table->string('cover_image_credit_url')->nullable()->after('cover_image_credit');
        });
    }

    public function down(): void
    {
        Schema::table('magazine_articles', function (Blueprint $table) {
            $table->dropColumn(['cover_image_credit', 'cover_image_credit_url']);
        });
    }
};
