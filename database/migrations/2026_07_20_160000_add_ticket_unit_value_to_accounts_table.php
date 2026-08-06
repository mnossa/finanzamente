<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('ticket_unit_value', 10, 2)->nullable()->after('interest_rate');
        });

        // Ambienti gia migrati: estendi ENUM type (MySQL). SQLite/test: create migration gia aggiornata.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('bank', 'cash', 'card', 'broker', 'crypto', 'meal_voucher', 'other') NOT NULL DEFAULT 'bank'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE accounts SET type = 'other' WHERE type = 'meal_voucher'");
            DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('bank', 'cash', 'card', 'broker', 'crypto', 'other') NOT NULL DEFAULT 'bank'");
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('ticket_unit_value');
        });
    }
};
