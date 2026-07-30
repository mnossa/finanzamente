<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('link_suggestions');
        Schema::dropIfExists('link_suggestion_runs');
        Schema::dropIfExists('magazine_articles');
        Schema::dropIfExists('magazine_categories');
        Schema::dropIfExists('product_analytics_daily');

        if (Schema::hasTable('retention_policies')) {
            DB::table('retention_policies')
                ->where('policy_key', 'product_analytics_daily')
                ->delete();
        }

        $disk = Storage::disk('public');

        if ($disk->exists('magazine/covers')) {
            $disk->deleteDirectory('magazine/covers');
        }

        if ($disk->exists('magazine') && empty($disk->directories('magazine')) && empty($disk->files('magazine'))) {
            $disk->deleteDirectory('magazine');
        }
    }

    public function down(): void
    {
        // Feature removed — no restore.
    }
};
