<?php

use App\Models\DashboardLayout;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * One-shot: Home → disposizione D3 (Saldo xl + Entrate/Uscite md + built-in md+md).
 */
return new class extends Migration
{
    public function up(): void
    {
        DashboardLayout::query()
            ->where('is_home', true)
            ->orderBy('id')
            ->each(function (DashboardLayout $home): void {
                $user = User::query()->find($home->user_id);

                $home->update([
                    'config' => $user !== null
                        ? DashboardLayout::essentialConfigForUser($user)
                        : DashboardLayout::essentialConfig(),
                ]);
            });
    }

    public function down(): void
    {
        // Irreversibile.
    }
};
