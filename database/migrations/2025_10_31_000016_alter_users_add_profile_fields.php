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
            // Active household (nullable until households exist for all users)
            $table->foreignId('active_household_id')->nullable()->constrained('households')->nullOnDelete()->after('id');

            // Split name into first/last while keeping `name` for backward compatibility
            $table->string('first_name')->nullable()->after('active_household_id');
            $table->string('last_name')->nullable()->after('first_name');

            // Additional profile fields
            $table->date('birth_date')->nullable()->after('password');
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active')->after('birth_date');
            $table->json('preferences')->nullable()->after('status');

            // Soft deletes on users
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop constraints first
            if (Schema::hasColumn('users', 'active_household_id')) {
                $table->dropForeign(['active_household_id']);
            }

            $drop = [];
            foreach (['active_household_id', 'first_name', 'last_name', 'birth_date', 'status', 'preferences', 'deleted_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $drop[] = $col;
                }
            }

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
