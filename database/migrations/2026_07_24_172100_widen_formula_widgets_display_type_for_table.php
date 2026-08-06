<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Convert legacy ENUM to VARCHAR so pie/treemap/table (and future types) are allowed.
            DB::statement("ALTER TABLE formula_widgets MODIFY COLUMN display_type VARCHAR(32) NOT NULL DEFAULT 'kpi'");
        }
        // SQLite / fresh installs: create migration already uses string(32).
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE formula_widgets MODIFY COLUMN display_type ENUM('kpi', 'line', 'area', 'bar', 'stacked_bar', 'progress', 'horizontal_bar', 'pie', 'treemap', 'table') NOT NULL DEFAULT 'kpi'");
        }
        // SQLite: leave as string — irreversible without rebuild.
    }
};
