<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->json('coupon_rate_steps')->nullable()->after('coupon_rate_percent');
        });
    }

    public function down(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->dropColumn('coupon_rate_steps');
        });
    }
};
