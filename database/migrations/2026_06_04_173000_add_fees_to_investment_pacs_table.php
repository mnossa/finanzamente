<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_pacs', function (Blueprint $table) {
            $table->decimal('fees', 10, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('investment_pacs', function (Blueprint $table) {
            $table->dropColumn('fees');
        });
    }
};
