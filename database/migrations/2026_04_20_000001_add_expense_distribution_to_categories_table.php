<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('expense_distribution', ['needs', 'wants', 'investments'])
                ->nullable()
                ->after('is_fixed_expense')
                ->comment('Classificazione per il widget distribuzione spese (Necessità, Extra, Investimenti)');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('expense_distribution');
        });
    }
};
