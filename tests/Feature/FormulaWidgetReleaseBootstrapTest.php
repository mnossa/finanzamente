<?php

namespace Tests\Feature;

use App\Models\DashboardLayout;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetReleaseBootstrapTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function release_bootstrap_command_seeds_templates_and_migrates_dashboards(): void
    {
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
                    ['id' => 'net_worth', 'visible' => true, 'position' => 1, 'size' => 'md'],
                    ['id' => 'accounts', 'visible' => true, 'position' => 2, 'size' => 'md'],
                ],
            ],
        ]);

        $exitCode = Artisan::call('formula-widgets:release-bootstrap', ['--user' => $user->id]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(10, FormulaWidget::query()->where('is_official_template', true)->count());
        $this->assertGreaterThan(0, FormulaWidget::query()->where('user_id', $user->id)->count());

        $layout = DashboardLayout::query()->where('user_id', $user->id)->first();
        $ids = array_column($layout->config['widgets'], 'id');

        $this->assertFalse(in_array('total_balance', $ids, true));
        $this->assertTrue(
            collect($ids)->contains(fn (string $id) => str_starts_with($id, 'formula_widget_')),
        );
    }

    #[Test]
    public function fresh_migrate_runs_one_shot_release_backfill(): void
    {
        // RefreshDatabase already executed all migrations, including the release backfill.
        $this->assertSame(10, FormulaWidget::query()->where('is_official_template', true)->count());
    }
}
