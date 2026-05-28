<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_pacs', function (Blueprint $table) {
            $table->boolean('adjust_for_inflation')->default(false)->after('amount');
            $table->decimal('inflation_rate_annual', 5, 2)->nullable()->after('adjust_for_inflation');
            $table->date('last_inflation_adjusted_at')->nullable()->after('inflation_rate_annual');
        });
    }

    public function down(): void
    {
        Schema::table('investment_pacs', function (Blueprint $table) {
            $table->dropColumn(['adjust_for_inflation', 'inflation_rate_annual', 'last_inflation_adjusted_at']);
        });
    }
};
