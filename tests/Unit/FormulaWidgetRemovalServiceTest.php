<?php

namespace Tests\Unit;

use App\Models\DashboardLayout;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\User;
use App\Services\FinancialVariableCloneService;
use App\Services\FormulaWidgetRemovalService;
use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetRemovalServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_catalog_official_template_is_protected_clone_can_uninstall(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $official = FormulaWidget::query()
            ->where('template_slug', 'official.saldo_liquidita')
            ->where('is_official_template', true)
            ->firstOrFail();

        $this->assertTrue($official->isOfficialProtected());
        $this->assertTrue($official->isOfficialOrigin());

        $user = User::factory()->create();
        $clone = app(FinancialVariableCloneService::class)->installTemplate($user, 'official.saldo_liquidita');

        $this->assertFalse($clone->isOfficialProtected());
        $this->assertTrue($clone->isOfficialOrigin());
    }

    #[Test]
    public function restore_migration_path_ensures_saldo_on_home(): void
    {
        Queue::fake();

        $this->seed(FormulaWidgetTemplateSeeder::class);

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        DashboardLayout::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfig(),
        ]);

        $migration = require database_path('migrations/2026_07_23_170100_restore_official_saldo_liquidita_on_home.php');
        $migration->up();

        $official = FormulaWidget::query()
            ->where('template_slug', 'official.saldo_liquidita')
            ->where('is_official_template', true)
            ->firstOrFail();

        $clone = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->where('source_id', $official->id)
            ->first();

        $this->assertNotNull($clone);

        $home = DashboardLayout::findHome($user->id, $household->id);
        $ids = array_column($home->config['widgets'] ?? [], 'id');
        $this->assertContains("formula_widget_{$clone->id}", $ids);
    }

    #[Test]
    public function soft_delete_then_purge_removes_row(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);

        $variable = FinancialVariable::factory()->for($user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($user)
            ->for($variable, 'financialVariable')
            ->create();

        $service = app(FormulaWidgetRemovalService::class);
        $service->remove($user, $widget);

        $this->assertSoftDeleted('formula_widgets', ['id' => $widget->id]);

        $service->purge(FormulaWidget::withTrashed()->findOrFail($widget->id));

        $this->assertDatabaseMissing('formula_widgets', ['id' => $widget->id]);
    }
}
