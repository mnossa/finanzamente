<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_import_layouts', function (Blueprint $table) {
            $table->string('model_type', 50)->default('transaction')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('bank_import_layouts', function (Blueprint $table) {
            $table->dropColumn('model_type');
        });
    }
};
