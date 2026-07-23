<?php

use App\Models\DashboardLayout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-shot: ripristina tutte le Home al layout Essenziale canonico (WFI-114).
 */
return new class extends Migration
{
    public function up(): void
    {
        $config = json_encode(DashboardLayout::essentialConfig());

        DB::table('dashboard_layouts')
            ->where('is_home', true)
            ->update([
                'config' => $config,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Irreversibile: le personalizzazioni Home precedenti non vengono ripristinate.
    }
};
