<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE investment_assets MODIFY COLUMN type ENUM('etf', 'stock', 'bond', 'index', 'commodity', 'insurance', 'crypto', 'other') NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('investment_assets')->where('type', 'bond')->update(['type' => 'other']);

        DB::statement("ALTER TABLE investment_assets MODIFY COLUMN type ENUM('crypto', 'etf', 'stock', 'index', 'commodity', 'insurance', 'other') NOT NULL DEFAULT 'other'");
    }
};
