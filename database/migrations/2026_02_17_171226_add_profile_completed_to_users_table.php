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
        Schema::table('users', function (Blueprint $table) {
            // Campo per tracciare se il quiz di profilazione è stato completato
            $table->boolean('profile_completed')->default(false)->after('preferences');

            // Campo JSON per salvare le risposte del quiz di profilazione
            $table->json('profile_settings')->nullable()->after('profile_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_completed', 'profile_settings']);
        });
    }
};
