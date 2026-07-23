<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\DashboardLayout;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\Transaction;
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

        DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Custom',
            'is_home' => false,
            'sort_order' => 1,
            'config' => ['widgets' => []],
        ]);
    }

    private function customBoard(): DashboardLayout
    {
        return DashboardLayout::query()
            ->where('user_id', $this->user->id)
            ->where('is_home', false)
            ->firstOrFail();
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
    public function store_rejects_duplicate_widget_and_suggests_existing_one(): void
    {
        $variableA = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $variableB = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $existing = FormulaWidget::factory()
            ->for($this->user)
            ->for($variableA, 'financialVariable')
            ->create([
                'name' => 'Widget esistente',
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
            ]);

        $this->actingAs($this->user)
            ->from(route('formula-widgets.create'))
            ->post(route('formula-widgets.store'), [
                'name' => 'Nuovo nome diverso',
                'financial_variable_id' => $variableB->id,
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
                'default_size' => 'md',
                'pin_to_dashboard' => false,
            ])
            ->assertRedirect(route('formula-widgets.create'))
            ->assertSessionHasErrors('widget')
            ->assertSessionHas('duplicateWidget.id', $existing->id);

        $this->assertSame(1, FormulaWidget::query()->where('user_id', $this->user->id)->count());
    }

    #[Test]
    public function store_rejects_create_when_equivalent_official_template_exists_in_marketplace(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $template = FormulaWidget::query()
            ->where('template_slug', 'official.saldo_liquidita')
            ->firstOrFail();

        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $this->actingAs($this->user)
            ->from(route('formula-widgets.create'))
            ->post(route('formula-widgets.store'), [
                'name' => 'Saldo manuale',
                'financial_variable_id' => $variable->id,
                'display_type' => $template->display_type,
                'period_preset' => $template->period_preset,
                'chart_config' => $template->chart_config,
                'default_size' => 'md',
                'pin_to_dashboard' => false,
            ])
            ->assertRedirect(route('formula-widgets.create'))
            ->assertSessionHasErrors('widget')
            ->assertSessionHas('duplicateMarketplaceWidget');

        $this->assertSame(0, FormulaWidget::query()->where('user_id', $this->user->id)->count());
    }

    #[Test]
    public function store_rejects_create_when_equivalent_community_widget_exists_in_marketplace(): void
    {
        $author = User::factory()->create();
        $authorVariable = FinancialVariable::factory()->for($author)->formula('[household_balance]')->create();
        FormulaWidget::factory()
            ->for($author)
            ->for($authorVariable, 'financialVariable')
            ->create([
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
                'is_public' => true,
                'share_token' => 'w_community2',
            ]);

        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $this->actingAs($this->user)
            ->from(route('formula-widgets.create'))
            ->post(route('formula-widgets.store'), [
                'name' => 'Copia community',
                'financial_variable_id' => $variable->id,
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
                'default_size' => 'md',
                'pin_to_dashboard' => false,
            ])
            ->assertRedirect(route('formula-widgets.create'))
            ->assertSessionHasErrors('widget')
            ->assertSessionHas('duplicateMarketplaceWidget');
    }

    #[Test]
    public function marketplace_install_rejects_duplicate_widget(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $template = FormulaWidget::query()
            ->where('template_slug', 'official.saldo_liquidita')
            ->with('financialVariable')
            ->firstOrFail();

        $clonedVariable = FinancialVariable::factory()
            ->for($this->user)
            ->formula((string) $template->financialVariable?->formula_string)
            ->create(['code' => 'saldo_liquidita']);

        FormulaWidget::factory()
            ->for($this->user)
            ->for($clonedVariable, 'financialVariable')
            ->create([
                'name' => 'Saldo manuale',
                'display_type' => $template->display_type,
                'period_preset' => $template->period_preset,
                'chart_config' => $template->chart_config,
            ]);

        $this->actingAs($this->user)
            ->from(route('formula-marketplace.index'))
            ->post(route('formula-marketplace.install-template', 'official.saldo_liquidita'))
            ->assertRedirect(route('formula-marketplace.index'))
            ->assertSessionHasErrors('widget')
            ->assertSessionHas('duplicateWidget');
    }

    #[Test]
    public function marketplace_install_rejects_duplicate_community_widget(): void
    {
        $author = User::factory()->create();
        $authorVariable = FinancialVariable::factory()
            ->for($author)
            ->formula('[household_balance]')
            ->create(['code' => 'saldo_community']);

        $publicWidget = FormulaWidget::factory()
            ->for($author)
            ->for($authorVariable, 'financialVariable')
            ->create([
                'name' => 'Saldo community',
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
                'is_public' => true,
                'share_token' => 'w_community_dup',
            ]);

        $clonedVariable = FinancialVariable::factory()
            ->for($this->user)
            ->formula('[household_balance]')
            ->create(['code' => 'saldo_manuale']);

        FormulaWidget::factory()
            ->for($this->user)
            ->for($clonedVariable, 'financialVariable')
            ->create([
                'name' => 'Saldo manuale',
                'display_type' => $publicWidget->display_type,
                'period_preset' => $publicWidget->period_preset,
                'chart_config' => $publicWidget->chart_config,
            ]);

        $this->actingAs($this->user)
            ->from(route('formula-marketplace.index'))
            ->post(route('formula-marketplace.install-widget', $publicWidget))
            ->assertRedirect(route('formula-marketplace.index'))
            ->assertSessionHasErrors('widget')
            ->assertSessionHas('duplicateWidget');
    }

    #[Test]
    public function marketplace_index_exposes_chart_types_for_filters(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $this->actingAs($this->user)
            ->get(route('formula-marketplace.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('chartTypes.kpi.label')
                ->has('officialTemplates.0.description'));
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
                ->has('officialTemplates', 10));

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
    public function preview_accepts_runtime_params_for_account_filter(): void
    {
        $accountA = Account::factory()->for($this->household)->for($this->user, 'owner')->create([
            'name' => 'Conto A',
        ]);

        $variable = FinancialVariable::factory()->for($this->user)->formula('[period_net]')->create();

        $allAccountsResponse = $this->actingAs($this->user)
            ->postJson(route('formula-widgets.preview'), [
                'financial_variable_id' => $variable->id,
                'display_type' => 'kpi',
                'period_preset' => 'current_month',
                'chart_config' => [
                    'format' => 'currency',
                    'parameters' => [
                        ['key' => 'account_id', 'type' => 'account', 'label' => 'Conto', 'default' => 'all'],
                    ],
                ],
                'runtime_params' => ['account_id' => 'all'],
            ])
            ->assertOk()
            ->assertJsonStructure(['payload' => ['parameters', 'value']]);

        $accountAResponse = $this->actingAs($this->user)
            ->postJson(route('formula-widgets.preview'), [
                'financial_variable_id' => $variable->id,
                'display_type' => 'kpi',
                'period_preset' => 'current_month',
                'chart_config' => [
                    'format' => 'currency',
                    'parameters' => [
                        ['key' => 'account_id', 'type' => 'account', 'label' => 'Conto', 'default' => 'all'],
                    ],
                ],
                'runtime_params' => ['account_id' => (string) $accountA->id],
            ])
            ->assertOk()
            ->assertJsonStructure(['payload' => ['parameters', 'value']]);

        $this->assertSame('all', $allAccountsResponse->json('payload.parameters.0.value'));
        $this->assertSame((string) $accountA->id, $accountAResponse->json('payload.parameters.0.value'));
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
            ->assertRedirect(route('dashboard', ['board' => $this->customBoard()->id]));

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
    public function authenticated_user_can_update_formula_widget(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create([
                'name' => 'Nome originale',
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
            ]);

        $this->actingAs($this->user)
            ->get(route('formula-widgets.edit', $widget))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FormulaWidgets/Create')
                ->where('editingWidget.id', $widget->id)
                ->where('editingWidget.name', 'Nome originale'));

        $this->actingAs($this->user)
            ->put(route('formula-widgets.update', $widget), [
                'name' => 'Nome aggiornato',
                'financial_variable_id' => $variable->id,
                'display_type' => 'kpi',
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
                'default_size' => 'md',
                'is_public' => false,
            ])
            ->assertRedirect(route('formula-widgets.index'));

        $this->assertDatabaseHas('formula_widgets', [
            'id' => $widget->id,
            'name' => 'Nome aggiornato',
        ]);
    }

    #[Test]
    public function destroy_removes_formula_widget_from_dashboard_layout(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create(['name' => 'Da rimuovere', 'display_type' => 'kpi']);

        $custom = $this->customBoard();

        $this->actingAs($this->user)
            ->post(route('formula-widgets.pin', $widget))
            ->assertRedirect(route('dashboard', ['board' => $custom->id]));

        $this->actingAs($this->user)
            ->delete(route('formula-widgets.destroy', $widget))
            ->assertRedirect(route('formula-widgets.index'));

        $layout = $custom->fresh();

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
            ->assertRedirect(route('dashboard', ['board' => $this->customBoard()->id]));

        $boardId = $this->customBoard()->id;

        $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('dashboard', ['board' => $boardId]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has("formulaWidgetPayloads.{$widget->id}")
                ->where("formulaWidgetPayloads.{$widget->id}.type", 'kpi')
                ->has("formulaWidgetMeta.{$widget->id}")
                ->has('formulaWidgetDataVersion'));

        $this->actingAs($this->user)
            ->getJson(route('dashboard.formula-widget-payloads', ['board' => $boardId]))
            ->assertOk()
            ->assertJsonPath("payloads.{$widget->id}.type", 'kpi')
            ->assertJsonPath("payloads.{$widget->id}.name", 'Saldo test');
    }

    #[Test]
    public function dashboard_ssr_priority_includes_only_kpi_and_progress_formula_widgets(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $kpiWidget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create([
                'name' => 'KPI saldo',
                'display_type' => 'kpi',
            ]);

        $chartWidget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create([
                'name' => 'Grafico saldo',
                'display_type' => 'bar',
            ]);

        $this->customBoard()->update([
            'config' => [
                'widgets' => [
                    ['id' => "formula_widget_{$chartWidget->id}", 'visible' => true, 'position' => 0, 'size' => 'md'],
                    ['id' => "formula_widget_{$kpiWidget->id}", 'visible' => true, 'position' => 1, 'size' => 'md'],
                ],
            ],
        ]);

        $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('dashboard', ['board' => $this->customBoard()->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has("formulaWidgetPayloads.{$kpiWidget->id}")
                ->where("formulaWidgetPayloads.{$kpiWidget->id}.type", 'kpi')
                ->missing("formulaWidgetPayloads.{$chartWidget->id}"));
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
            ->assertRedirect(route('dashboard', ['board' => $this->customBoard()->id]));

        $first = $this->actingAs($this->user)
            ->getJson(route('dashboard.formula-widget-payloads', ['board' => $this->customBoard()->id]));

        $first->assertOk();
        $cacheControl = (string) $first->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertNotEmpty($first->headers->get('ETag'));

        $etag = $first->headers->get('ETag');

        $this->actingAs($this->user)
            ->withHeaders(['If-None-Match' => $etag])
            ->getJson(route('dashboard.formula-widget-payloads', ['board' => $this->customBoard()->id]))
            ->assertStatus(304);
    }

    #[Test]
    public function formula_widget_payloads_partial_request_never_returns_not_modified(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widgetA = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create(['display_type' => 'kpi', 'name' => 'Widget A']);
        $widgetB = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create(['display_type' => 'line', 'period_preset' => 'rolling_30', 'name' => 'Widget B']);

        $this->actingAs($this->user)
            ->post(route('formula-widgets.pin', $widgetA))
            ->assertRedirect(route('dashboard', ['board' => $this->customBoard()->id]));
        $this->actingAs($this->user)
            ->post(route('formula-widgets.pin', $widgetB))
            ->assertRedirect(route('dashboard', ['board' => $this->customBoard()->id]));

        $full = $this->actingAs($this->user)
            ->getJson(route('dashboard.formula-widget-payloads', ['board' => $this->customBoard()->id]));

        $full->assertOk();
        $etag = $full->headers->get('ETag');

        $this->actingAs($this->user)
            ->withHeaders(['If-None-Match' => $etag])
            ->getJson(route('dashboard.formula-widget-payloads', ['board' => $this->customBoard()->id, 'ids' => (string) $widgetB->id]))
            ->assertOk()
            ->assertJsonPath("payloads.{$widgetB->id}.type", 'line');
    }

    #[Test]
    public function formula_widget_payloads_endpoint_supports_partial_ids_and_data_version(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create(['display_type' => 'kpi', 'name' => 'Widget parziale']);

        $this->actingAs($this->user)
            ->post(route('formula-widgets.pin', $widget))
            ->assertRedirect(route('dashboard', ['board' => $this->customBoard()->id]));

        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.formula-widget-payloads', ['board' => $this->customBoard()->id, 'ids' => (string) $widget->id]));

        $response->assertOk()
            ->assertJsonPath("payloads.{$widget->id}.name", 'Widget parziale')
            ->assertJsonStructure(['dataVersion'])
            ->assertHeader('X-Formula-Widget-Data-Version');
    }

    #[Test]
    public function formula_widget_payloads_etag_changes_after_transaction_mutation(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();
        $widget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create(['display_type' => 'kpi']);

        $this->actingAs($this->user)
            ->post(route('formula-widgets.pin', $widget))
            ->assertRedirect(route('dashboard', ['board' => $this->customBoard()->id]));

        $account = Account::factory()->for($this->user->activeHousehold)->create();
        $transaction = Transaction::factory()->for($account)->create();

        $before = $this->actingAs($this->user)
            ->getJson(route('dashboard.formula-widget-payloads', ['board' => $this->customBoard()->id]))
            ->headers->get('ETag');

        Transaction::factory()->for($account)->create();

        $after = $this->actingAs($this->user)
            ->getJson(route('dashboard.formula-widget-payloads', ['board' => $this->customBoard()->id]))
            ->headers->get('ETag');

        $this->assertNotSame($before, $after);
    }

    #[Test]
    public function dashboard_ssr_prioritizes_kpi_formula_widgets_within_priority_payload_limit(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        $chartWidgets = collect(range(0, 3))->map(fn (int $index) => FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create([
                'name' => "Chart {$index}",
                'display_type' => 'bar',
            ]));

        $kpiWidget = FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create([
                'name' => 'KPI prioritario',
                'display_type' => 'kpi',
            ]);

        $widgets = $chartWidgets
            ->map(fn (FormulaWidget $widget, int $index) => [
                'id' => "formula_widget_{$widget->id}",
                'visible' => true,
                'position' => $index,
                'size' => 'md',
            ])
            ->push([
                'id' => "formula_widget_{$kpiWidget->id}",
                'visible' => true,
                'position' => 4,
                'size' => 'md',
            ])
            ->all();

        $this->customBoard()->update([
            'config' => ['widgets' => $widgets],
        ]);

        $excludedChart = $chartWidgets->last();

        $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('dashboard', ['board' => $this->customBoard()->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has("formulaWidgetPayloads.{$kpiWidget->id}")
                ->missing("formulaWidgetPayloads.{$excludedChart->id}")
            );
    }

    #[Test]
    public function preview_and_store_support_metric_query_widget(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[period_net]')->create();

        $chartConfig = [
            'format' => 'number',
            'metric_query' => [
                'datasource' => 'transactions',
                'measure' => 'count',
                'amount_field' => 'amount_base',
                'filters' => [
                    ['field' => 'transaction_type', 'operator' => 'eq', 'value' => 'expense'],
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->postJson(route('formula-widgets.preview'), [
                'financial_variable_id' => $variable->id,
                'display_type' => 'kpi',
                'period_preset' => 'current_month',
                'chart_config' => $chartConfig,
            ])
            ->assertOk()
            ->assertJsonPath('payload.type', 'kpi');

        $this->actingAs($this->user)
            ->post(route('formula-widgets.store'), [
                'name' => 'Conteggio spese',
                'financial_variable_id' => $variable->id,
                'display_type' => 'kpi',
                'period_preset' => 'current_month',
                'chart_config' => $chartConfig,
                'default_size' => 'md',
                'pin_to_dashboard' => false,
            ])
            ->assertRedirect(route('formula-widgets.index'));

        $this->assertDatabaseHas('formula_widgets', [
            'user_id' => $this->user->id,
            'name' => 'Conteggio spese',
        ]);
    }
}
