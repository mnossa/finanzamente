<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->string('day_of_month_mode', 20)->default('start_date')->after('frequency');
            $table->unsignedTinyInteger('day_of_month')->nullable()->after('day_of_month_mode');
            $table->string('non_working_day_policy', 20)->default('postpone')->after('day_of_month');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'day_of_month_mode',
                'day_of_month',
                'non_working_day_policy',
            ]);
        });
    }
};
