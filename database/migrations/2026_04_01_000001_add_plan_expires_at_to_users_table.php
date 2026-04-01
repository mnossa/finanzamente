<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Data/ora di scadenza del piano Pro (null = nessuna scadenza impostata)
            // Viene settato quando l'utente cancella l'abbonamento ma ha ancora
            // il periodo pagato da completare (grace period).
            $table->timestamp('plan_expires_at')->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('plan_expires_at');
        });
    }
};
