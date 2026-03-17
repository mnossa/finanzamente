<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Chiave univoca per prevenire notifiche duplicate (es. "budget_42_80_2026-03")
            $table->string('notification_key')->nullable()->after('read');
            $table->index(['user_id', 'notification_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'notification_key']);
            $table->dropColumn('notification_key');
        });
    }
};
