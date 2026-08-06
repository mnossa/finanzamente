<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('event_kind', 32);
            $table->string('feature_key', 64);
            $table->string('event_name', 128);
            $table->string('dimensions_hash', 64)->default('');
            $table->json('dimensions')->nullable();
            $table->unsignedInteger('event_count')->default(0);
            $table->timestamps();

            $table->unique(
                ['day', 'event_kind', 'feature_key', 'event_name', 'dimensions_hash'],
                'product_analytics_daily_unique'
            );
            $table->index(['day', 'event_kind']);
            $table->index(['feature_key', 'day']);
        });

        DB::table('retention_policies')->insert([
            'policy_key' => 'product_analytics_daily',
            'description' => 'Aggregati product analytics first-party (nessuna PII).',
            'retention_days' => 90,
            'anonymize_after_days' => null,
            'is_active' => true,
            'version' => '2026-07-27-v1',
            'metadata' => json_encode(['store' => 'aggregates_only']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('retention_policies')->where('policy_key', 'product_analytics_daily')->delete();
        Schema::dropIfExists('product_analytics_daily');
    }
};
