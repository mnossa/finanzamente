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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('user_type', ['persona', 'partita_iva'])->default('persona')->after('email');
            $table->string('fiscal_code', 16)->nullable()->after('user_type');
            $table->string('vat_number', 11)->nullable()->after('fiscal_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['user_type', 'fiscal_code', 'vat_number']);
        });
    }
};
