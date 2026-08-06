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
        Schema::table('attachments', function (Blueprint $table) {
            $table->morphs('attachable');
            $table->string('filename')->after('file_path')->comment('Nome originale del file');
            $table->string('mime_type')->nullable()->after('filename')->comment('Tipo MIME del file');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type')->comment('Dimensione del file in bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropMorphs('attachable');
            $table->dropColumn(['filename', 'mime_type', 'file_size']);
        });
    }
};
