<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('investment_event', 32)->nullable()->after('investment_id');
            $table->index(['investment_id', 'investment_event']);
        });

        // Backfill capital ledger: importi negativi = purchase, positivi = sale.
        DB::table('transactions')
            ->whereNotNull('investment_id')
            ->whereNull('investment_event')
            ->where('amount', '<', 0)
            ->update(['investment_event' => 'purchase']);

        DB::table('transactions')
            ->whereNotNull('investment_id')
            ->whereNull('investment_event')
            ->where('amount', '>', 0)
            ->update(['investment_event' => 'sale']);

        Schema::table('investment_assets', function (Blueprint $table) {
            $table->string('coupon_frequency', 32)->nullable()->after('allocation_asset_class');
            $table->date('next_coupon_date')->nullable()->after('coupon_frequency');
            $table->decimal('coupon_rate_percent', 8, 4)->nullable()->after('next_coupon_date');
        });
    }

    public function down(): void
    {
        Schema::table('investment_assets', function (Blueprint $table) {
            $table->dropColumn(['coupon_frequency', 'next_coupon_date', 'coupon_rate_percent']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['investment_id', 'investment_event']);
            $table->dropColumn('investment_event');
        });
    }
};
