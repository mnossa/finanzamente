<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->boolean('exclude_inter_transfers_from_stats')
                ->default(false)
                ->after('enable_turn_suggestions')
                ->comment('Se true, i trasferimenti inter-household che coinvolgono questa household avranno exclude_from_stats=true per impostazione predefinita');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('exclude_inter_transfers_from_stats');
        });
    }
};
