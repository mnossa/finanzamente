<?php

namespace Tests\Feature;

use App\Models\DashboardLayout;
use App\Models\Household;
use App\Models\User;
use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardLayoutMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migrate_command_replaces_tier_a_legacy_widgets_with_formula_widgets(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $user->update(['active_household_id' => $household->id]);

        DashboardLayout::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'config' => [
                'widgets' => [
                    ['id' => 'total_balance', 'visible' => true, 'position' => 0, 'size' => 'xl'],
                    ['id' => 'monthly_stats', 'visible' => true, 'position' => 1, 'size' => 'xl'],
                    ['id' => 'accounts', 'visible' => true, 'position' => 2, 'size' => 'md'],
                ],
            ],
        ]);

        Artisan::call('formula-widgets:migrate-dashboard-layouts', ['--user' => $user->id]);

        $layout = DashboardLayout::query()->where('user_id', $user->id)->first();
        $ids = array_column($layout->config['widgets'], 'id');

        $this->assertFalse(in_array('total_balance', $ids, true));
        $this->assertFalse(in_array('monthly_stats', $ids, true));
        $this->assertTrue(
            collect($ids)->contains(fn (string $id) => str_starts_with($id, 'formula_widget_')),
        );
    }
}
