<?php

use App\Models\DashboardLayout;
use App\Models\User;
use App\Services\FormulaWidgetLayoutNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Allinea layout dashboard (dimensioni griglia + saldo composto) dopo il refactor Tier A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('formula-templates:seed');

        $normalizer = app(FormulaWidgetLayoutNormalizer::class);

        User::query()->whereNotNull('active_household_id')->each(function (User $user) use ($normalizer) {
            $normalizer->syncTemplateConfigs($user);

            $layout = DashboardLayout::query()
                ->where('user_id', $user->id)
                ->where('household_id', $user->active_household_id)
                ->first();

            if ($layout === null) {
                return;
            }

            $layout->update([
                'config' => $normalizer->normalize($user, $layout->config ?? []),
            ]);
        });
    }

    public function down(): void
    {
        // Non reversibile.
    }
};
