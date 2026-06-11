<?php

namespace Tests\Feature;

use App\Models\DashboardLayout;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\User;
use App\Services\FormulaWidgetBootstrapService;
use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetBootstrapServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_provisions_default_widgets_and_dashboard_layout_for_new_user(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $user->update(['active_household_id' => $household->id]);

        app(FormulaWidgetBootstrapService::class)->provisionForUser($user);

        $this->assertGreaterThan(0, FormulaWidget::query()->where('user_id', $user->id)->count());

        $layout = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $household->id)
            ->first();

        $this->assertNotNull($layout);
        $widgetIds = array_column($layout->config['widgets'], 'id');
        $this->assertTrue(
            collect($widgetIds)->contains(fn (string $id) => str_starts_with($id, 'formula_widget_')),
        );
    }
}
