<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->string('allocation_asset_class', 32)
                ->nullable()
                ->after('type')
                ->comment('Override classe allocazione: equities, bonds, commodities, crypto, other');
        });
    }

    public function down(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->dropColumn('allocation_asset_class');
        });
    }
};
