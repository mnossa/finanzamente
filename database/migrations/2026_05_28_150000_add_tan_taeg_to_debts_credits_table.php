<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts_credits', function (Blueprint $table) {
            $table->decimal('tan_rate', 5, 2)->nullable()->after('interest_rate');
            $table->decimal('taeg_rate', 5, 2)->nullable()->after('tan_rate');
        });
    }

    public function down(): void
    {
        Schema::table('debts_credits', function (Blueprint $table) {
            $table->dropColumn(['tan_rate', 'taeg_rate']);
        });
    }
};
