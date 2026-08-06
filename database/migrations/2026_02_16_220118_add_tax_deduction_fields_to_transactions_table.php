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
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_tax_deductible')->default(false)->after('is_private');
            $table->decimal('tax_deduction_rate', 5, 2)->nullable()->after('is_tax_deductible')->comment('Percentuale di detrazione (es. 19.00 per 19%)');
            $table->string('tax_deduction_type', 50)->nullable()->after('tax_deduction_rate')->comment('Tipo di detrazione (es. mediche, veterinarie, istruzione, mutuo)');
            $table->year('tax_year')->nullable()->after('tax_deduction_type')->comment('Anno fiscale di riferimento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['is_tax_deductible', 'tax_deduction_rate', 'tax_deduction_type', 'tax_year']);
        });
    }
};
