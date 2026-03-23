<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inter_household_transfers', function (Blueprint $table) {
            $table->boolean('exclude_from_stats')
                ->default(false)
                ->after('transfer_date')
                ->comment('Se true, le transazioni generate da questo trasferimento sono escluse dai calcoli statistici (es. spostamenti interni tra proprie households)');
        });
    }

    public function down(): void
    {
        Schema::table('inter_household_transfers', function (Blueprint $table) {
            $table->dropColumn('exclude_from_stats');
        });
    }
};
