<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->foreignId('investment_pac_id')
                ->nullable()
                ->after('asset_id')
                ->constrained('investment_pacs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('investment_pac_id');
        });
    }
};
