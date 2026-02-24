<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_import_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->string('name');
            $table->string('bank_name'); // nome banca associata al layout (es. custom)
            $table->json('column_mapping'); // maps CSV column indices/names to transaction fields
            $table->string('delimiter', 5)->default(',');
            $table->string('date_format', 50)->default('d/m/Y');
            $table->boolean('has_header')->default(true);
            $table->string('encoding', 20)->default('UTF-8');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_import_layouts');
    }
};
