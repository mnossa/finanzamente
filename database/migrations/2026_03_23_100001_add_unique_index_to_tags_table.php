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
        // Normalizza i nomi dei tag esistenti in uppercase prima di aggiungere il vincolo
        DB::table('tags')->whereNull('deleted_at')->update([
            'name' => DB::raw('UPPER(name)'),
        ]);

        // Aggiungi l'indice unico su (household_id, name) per garantire unicità case-insensitive
        Schema::table('tags', function (Blueprint $table) {
            $table->unique(['household_id', 'name'], 'tags_household_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique('tags_household_name_unique');
        });
    }
};
