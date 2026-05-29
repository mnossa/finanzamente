<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duplicate_transaction_candidates', function (Blueprint $table) {
            $table->json('cluster_transaction_ids')->nullable()->after('distance_days');
        });
    }

    public function down(): void
    {
        Schema::table('duplicate_transaction_candidates', function (Blueprint $table) {
            $table->dropColumn('cluster_transaction_ids');
        });
    }
};
