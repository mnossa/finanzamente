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
            $table->boolean('enable_turn_suggestions')
                ->default(false)
                ->comment('Abilita il suggeritore di turni per le spese fisse');

            $table->json('turn_suggestion_settings')
                ->nullable()
                ->comment('Impostazioni del suggeritore di turni (categorie, frequenza, ecc.)');

            $table->json('last_turn_assignments')
                ->nullable()
                ->comment('Ultimi turni assegnati per categoria fissa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['enable_turn_suggestions', 'turn_suggestion_settings', 'last_turn_assignments']);
        });
    }
};
