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
            $table->string('external_url', 500)->nullable()->after('ticket_unit_value');
        });

        // MySQL: estendi ENUM. SQLite/test: create migration già aggiornata.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('bank', 'cash', 'card', 'broker', 'crypto', 'meal_voucher', 'pension_fund', 'other') NOT NULL DEFAULT 'bank'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE accounts SET type = 'other' WHERE type = 'pension_fund'");
            DB::statement("ALTER TABLE accounts MODIFY COLUMN type ENUM('bank', 'cash', 'card', 'broker', 'crypto', 'meal_voucher', 'other') NOT NULL DEFAULT 'bank'");
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('external_url');
        });
    }
};
