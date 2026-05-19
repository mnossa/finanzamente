<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('split_group_id')->nullable()->after('debt_credit_id')->index();
            $table->boolean('is_split_primary')->default(false)->after('split_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['split_group_id', 'is_split_primary']);
        });
    }
};
