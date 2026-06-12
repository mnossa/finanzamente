<?php

namespace Tests\Feature;

use App\Models\DashboardLayout;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\User;
use Database\Seeders\FormulaWidgetTemplateSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function guest_can_preview_shared_formula_widget(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create([
                'name' => 'Widget demo',
                'is_public' => true,
                'share_token' => 'w_demo123456',
            ]);

        $this->get(route('shared.formula.show', $widget->share_token))
            ->assertOk()
            ->assertSee('Widget demo')
            ->assertSee('Anteprima demo');
    }

    #[Test]
    public function authenticated_user_can_list_and_create_formula_widgets(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $this->actingAs($this->user)
            ->get(route('formula-widgets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('FormulaWidgets/Index'));

        $this->actingAs($this->user)
            ->post(route('formula-widgets.store'), [
                'name' => 'Saldo KPI',
                'financial_variable_id' => $variable->id,
                'display_type' => 'kpi',
                'period_preset' => 'rolling_30',
                'chart_config' => ['format' => 'currency'],
                'default_size' => 'md',
                'pin_to_dashboard' => false,
            ])
            ->assertRedirect(route('formula-widgets.index'));

        $this->assertDatabaseHas('formula_widgets', [
            'user_id' => $this->user->id,
            'name' => 'Saldo KPI',
            'display_type' => 'kpi',
        ]);
    }

    #[Test]
    public function authenticated_user_can_manage_financial_variables(): void
    {
        $this->actingAs($this->user)
            ->get(route('formula-variables.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('FormulaWidgets/Variables/Index'));

        $this->actingAs($this->user)
            ->post(route('formula-variables.store'), [
                'name' => 'Saldo custom',
                'type' => FinancialVariable::TYPE_FORMULA,
                'formula_string' => '[household_balance]',
            ])
            ->assertRedirect(route('formula-variables.index'));

        $this->assertDatabaseHas('financial_variables', [
            'user_id' => $this->user->id,
            'name' => 'Saldo custom',
            'type' => FinancialVariable::TYPE_FORMULA,
        ]);
    }

    #[Test]
    public function marketplace_lists_official_templates_and_installs_one(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $this->actingAs($this->user)
            ->get(route('formula-marketplace.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FormulaWidgets/Marketplace')
                ->has('officialTemplates', 11));

        $this->actingAs($this->user)
            ->post(route('formula-marketplace.install-template', 'official.saldo_liquidita'))
            ->assertRedirect(route('formula-widgets.index'));

        $this->assertTrue(
            FormulaWidget::query()
                ->where('user_id', $this->user->id)
                ->where('is_official_template', false)
                ->whereHas('financialVariable', fn ($q) => $q->where('code', 'saldo_liquidita'))
                ->exists()
        );
    }

    #[Test]
    public function preview_returns_live_payload_for_valid_widget_config(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $this->actingAs($this->user)
            ->postJson(route('formula-widgets.preview'), [
                'name' => 'Anteprima saldo',
                'financial_variable_id' => $variable->id,
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
            ])
            ->assertOk()
            ->assertJsonStructure(['payload' => ['type', 'name', 'value']])
            ->assertJsonPath('payload.type', 'kpi')
            ->assertJsonPath('payload.name', 'Anteprima saldo');
    }

    #[Test]
    public function preview_returns_pie_payload_for_bar_style_series(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $this->actingAs($this->user)
            ->postJson(route('formula-widgets.preview'), [
                'financial_variable_id' => $variable->id,
                'display_type' => 'pie',
                'period_preset' => 'rolling_30',
                'chart_config' => [
                    'series' => [
                        ['code' => 'household_balance', 'label' => 'Liquidità'],
                        ['code' => 'total_investments', 'label' => 'Investimenti'],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('payload.type', 'pie')
            ->assertJsonStructure(['payload' => ['categories']]);
    }

    #[Test]
    public function store_financial_variable_returns_json_for_inline_creation(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('formula-variables.store'), [
                'name' => 'Saldo rapido',
                'type' => FinancialVariable::TYPE_FORMULA,
                'formula_string' => '[household_balance]',
            ])
            ->assertOk()
            ->assertJsonStructure(['variable' => ['id', 'code', 'name']]);
    }

    #[Test]
    public function preview_returns_validation_errors_for_incomplete_config(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[period_income]')->create();

        $this->actingAs($this->user)
            ->postJson(route('formula-widgets.preview'), [
                'financial_variable_id' => $variable->id,
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['show_delta' => true, 'format' => 'currency'],
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors']);
    }

    #[Test]
    public function marketplace_preview_returns_live_payload_for_official_template(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $this->actingAs($this->user)
            ->postJson(route('formula-marketplace.preview'), [
                'template_slug' => 'official.saldo_liquidita',
            ])
            ->assertOk()
            ->assertJsonStructure(['payload' => ['type', 'name', 'value']])
            ->assertJsonPath('payload.type', 'kpi')
            ->assertJsonPath('payload.name', 'Saldo conti');
    }

    #[Test]
    public function marketplace_can_uninstall_installed_template(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $this->actingAs($this->user)
            ->post(route('formula-marketplace.install-template', 'official.saldo_liquidita'), ['pin' => true])
            ->assertRedirect(route('dashboard'));

        $installed = FormulaWidget::query()
            ->where('user_id', $this->user->id)
            ->where('is_official_template', false)
            ->first();

        $this->assertNotNull($installed);

        $this->actingAs($this->user)
            ->delete(route('formula-marketplace.uninstall-template', 'official.saldo_liquidita'))
            ->assertRedirect(route('formula-marketplace.index'));

        $this->assertDatabaseMissing('formula_widgets', ['id' => $installed->id]);
    }

    #[Test]
    public function destroy_removes_formula_widget_from_dashboard_layout(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create(['name' => 'Da rimuovere', 'display_type' => 'kpi']);

        $this->actingAs($this->user)
            ->post(route('formula-widgets.pin', $widget))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($this->user)
            ->delete(route('formula-widgets.destroy', $widget))
            ->assertRedirect(route('formula-widgets.index'));

        $layout = DashboardLayout::query()
            ->where('user_id', $this->user->id)
            ->where('household_id', $this->household->id)
            ->first();

        $ids = array_column($layout->config['widgets'], 'id');
        $this->assertNotContains("formula_widget_{$widget->id}", $ids);
    }

    #[Test]
    public function dashboard_includes_priority_formula_payloads_and_defers_rest_to_async_endpoint(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create([
                'name' => 'Saldo test',
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
            ]);

        $this->actingAs($this->user)
            ->post(route('formula-widgets.pin', $widget))
            ->assertRedirect(route('dashboard'));

        $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has("formulaWidgetPayloads.{$widget->id}")
                ->where("formulaWidgetPayloads.{$widget->id}.type", 'kpi')
                ->has("formulaWidgetMeta.{$widget->id}"));

        $this->actingAs($this->user)
            ->getJson(route('dashboard.formula-widget-payloads'))
            ->assertOk()
            ->assertJsonPath("payloads.{$widget->id}.type", 'kpi')
            ->assertJsonPath("payloads.{$widget->id}.name", 'Saldo test');
    }

    #[Test]
    public function lifestyle_score_official_template_is_retired_from_marketplace(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $this->actingAs($this->user)
            ->get(route('formula-marketplace.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('officialTemplates', fn ($templates) => collect($templates)
                    ->every(fn (array $template) => ($template['template_slug'] ?? '') !== 'official.lifestyle_score')));
    }

    #[Test]
    public function formula_widget_payloads_endpoint_exposes_private_http_cache_headers(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create(['display_type' => 'kpi']);

        $this->actingAs($this->user)
            ->post(route('formula-widgets.pin', $widget))
            ->assertRedirect(route('dashboard'));

        $first = $this->actingAs($this->user)
            ->getJson(route('dashboard.formula-widget-payloads'));

        $first->assertOk();
        $cacheControl = (string) $first->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertNotEmpty($first->headers->get('ETag'));

        $etag = $first->headers->get('ETag');

        $this->actingAs($this->user)
            ->withHeaders(['If-None-Match' => $etag])
            ->getJson(route('dashboard.formula-widget-payloads'))
            ->assertStatus(304);
    }
}
