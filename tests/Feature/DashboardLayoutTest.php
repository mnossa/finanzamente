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

class DashboardLayoutTest extends TestCase
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

    private function editableBoard(array $config = ['widgets' => []]): DashboardLayout
    {
        return DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Custom '.uniqid(),
            'is_home' => false,
            'sort_order' => 1,
            'config' => $config,
        ]);
    }

    private function createUserFormulaWidget(array $attributes = []): FormulaWidget
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[household_balance]')->create();

        return FormulaWidget::factory()
            ->for($this->user)
            ->for($variable, 'financialVariable')
            ->create(array_merge([
                'period_preset' => null,
                'chart_config' => ['format' => 'currency'],
            ], $attributes));
    }

    // ─── GET /dashboard/layout ─────────────────────────────────────────────

    #[Test]
    public function unauthenticated_user_cannot_access_layout(): void
    {
        $this->get(route('dashboard.layout.show'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_user_receives_default_layout_when_none_saved(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.layout.show'));

        $response->assertOk();
        $response->assertJsonStructure(['config' => ['widgets']]);
        $widgets = $response->json('config.widgets');

        $this->assertIsArray($widgets);
        $this->assertCount(4, $widgets);
        $this->assertSame(
            ['active_budgets', 'expense_treemap', 'recent_transactions', 'accounts'],
            array_column($widgets, 'id'),
        );
    }

    #[Test]
    public function authenticated_user_receives_saved_layout(): void
    {
        $config = [
            'widgets' => [
                ['id' => 'expense_distribution', 'visible' => true,  'position' => 0, 'size' => 'lg'],
                ['id' => 'accounts',             'visible' => false, 'position' => 1, 'size' => 'lg'],
                ['id' => 'lifestyle_widget',     'visible' => true,  'position' => 4, 'size' => 'lg'],
                ['id' => 'recent_transactions',  'visible' => true,  'position' => 5, 'size' => 'md'],
                ['id' => 'active_budgets',       'visible' => true,  'position' => 6, 'size' => 'md'],
                ['id' => 'debts_credits',        'visible' => true,  'position' => 7, 'size' => 'md'],
            ],
        ];

        $board = $this->editableBoard($config);

        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.layout.show', ['board' => $board->id]));

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'expense_distribution']);
        $widgets = $response->json('config.widgets');
        $accountsWidget = collect($widgets)->firstWhere('id', 'accounts');
        $this->assertFalse($accountsWidget['visible']);
    }

    // ─── POST /dashboard/layout ────────────────────────────────────────────

    #[Test]
    public function user_can_save_valid_layout(): void
    {
        $board = $this->editableBoard();

        $payload = [
            'board' => $board->id,
            'config' => [
                'widgets' => [
                    ['id' => 'accounts',             'visible' => true, 'position' => 0, 'size' => 'lg'],
                    ['id' => 'lifestyle_widget',     'visible' => true, 'position' => 3, 'size' => 'lg'],
                    ['id' => 'recent_transactions',  'visible' => true, 'position' => 4, 'size' => 'md'],
                    ['id' => 'active_budgets',       'visible' => true, 'position' => 5, 'size' => 'md'],
                    ['id' => 'debts_credits',        'visible' => true, 'position' => 6, 'size' => 'md'],
                    ['id' => 'expense_distribution', 'visible' => true, 'position' => 7, 'size' => 'lg'],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), $payload);

        $response->assertOk();
        $response->assertJsonStructure(['config', 'message']);

        $this->assertDatabaseHas('dashboard_layouts', [
            'id' => $board->id,
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'is_home' => false,
        ]);
    }

    #[Test]
    public function saving_layout_updates_existing_record(): void
    {
        $board = $this->editableBoard();

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), [
                'board' => $board->id,
                'config' => [
                    'widgets' => [
                        ['id' => 'accounts',             'visible' => true, 'position' => 0, 'size' => 'lg'],
                        ['id' => 'lifestyle_widget',     'visible' => true, 'position' => 3, 'size' => 'lg'],
                        ['id' => 'recent_transactions',  'visible' => true, 'position' => 4, 'size' => 'md'],
                        ['id' => 'active_budgets',       'visible' => true, 'position' => 5, 'size' => 'md'],
                        ['id' => 'debts_credits',        'visible' => true, 'position' => 6, 'size' => 'md'],
                        ['id' => 'expense_distribution', 'visible' => true, 'position' => 7, 'size' => 'lg'],
                    ],
                ],
            ]);

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), [
                'board' => $board->id,
                'config' => [
                    'widgets' => [
                        ['id' => 'expense_distribution', 'visible' => true,  'position' => 0, 'size' => 'lg'],
                        ['id' => 'accounts',             'visible' => false, 'position' => 1, 'size' => 'lg'],
                        ['id' => 'lifestyle_widget',     'visible' => true,  'position' => 4, 'size' => 'lg'],
                        ['id' => 'recent_transactions',  'visible' => true,  'position' => 5, 'size' => 'md'],
                        ['id' => 'active_budgets',       'visible' => true,  'position' => 6, 'size' => 'md'],
                        ['id' => 'debts_credits',        'visible' => true,  'position' => 7, 'size' => 'md'],
                    ],
                ],
            ]);

        $this->assertCount(
            1,
            DashboardLayout::where('user_id', $this->user->id)
                ->where('household_id', $this->household->id)
                ->where('is_home', false)
                ->get()
        );

        $layout = $board->fresh();
        $first = collect($layout->config['widgets'])->firstWhere('id', 'expense_distribution');
        $this->assertEquals(0, $first['position']);
    }

    #[Test]
    public function saving_layout_with_quick_actions_strips_it_silently(): void
    {
        $board = $this->editableBoard();

        $payload = [
            'board' => $board->id,
            'config' => [
                'widgets' => [
                    ['id' => 'accounts', 'visible' => true, 'position' => 0, 'size' => 'lg'],
                    ['id' => 'quick_actions', 'visible' => true, 'position' => 1, 'size' => 'md'],
                    ['id' => 'active_budgets', 'visible' => true, 'position' => 2, 'size' => 'md'],
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), $payload)
            ->assertOk();

        $ids = array_column($board->fresh()->config['widgets'], 'id');
        $this->assertSame(['accounts', 'active_budgets'], $ids);
        $this->assertNotContains('quick_actions', $ids);
    }

    #[Test]
    public function saving_layout_with_invalid_widget_id_fails(): void
    {
        $board = $this->editableBoard();

        $payload = [
            'board' => $board->id,
            'config' => [
                'widgets' => [
                    ['id' => 'widget_inesistente', 'visible' => true, 'position' => 0, 'size' => 'lg'],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), $payload);

        $response->assertUnprocessable();
        $response->assertJsonFragment(['config.widgets' => ['Uno o più widget non sono riconosciuti: widget_inesistente']]);
    }

    #[Test]
    public function saving_layout_with_invalid_size_fails(): void
    {
        $board = $this->editableBoard();

        $payload = [
            'board' => $board->id,
            'config' => [
                'widgets' => [
                    ['id' => 'accounts', 'visible' => true, 'position' => 0, 'size' => 'xxl'],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), $payload);

        $response->assertUnprocessable();
    }

    #[Test]
    public function saving_layout_without_config_fails(): void
    {
        $board = $this->editableBoard();

        $response = $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), ['board' => $board->id]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['config']);
    }

    #[Test]
    public function user_cannot_save_layout_without_authentication(): void
    {
        $this->postJson(route('dashboard.layout.store'), [
            'config' => ['widgets' => []],
        ])->assertUnauthorized();
    }

    // ─── DELETE /dashboard/layout ──────────────────────────────────────────

    #[Test]
    public function user_can_reset_layout_to_default(): void
    {
        $board = $this->editableBoard(['widgets' => []]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('dashboard.layout.reset', ['board' => $board->id]));

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'accounts']);

        $board->refresh();
        $this->assertSame(
            array_column(DashboardLayout::essentialConfigForUser($this->user)['widgets'], 'id'),
            array_column($board->config['widgets'], 'id'),
        );
        $this->assertDatabaseHas('dashboard_layouts', [
            'id' => $board->id,
            'is_home' => false,
        ]);
    }

    #[Test]
    public function resetting_layout_with_no_saved_layout_creates_home_default(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson(route('dashboard.layout.reset'));

        $response->assertOk();
        $response->assertJsonStructure(['config' => ['widgets']]);
        $this->assertSame(
            array_column(DashboardLayout::essentialConfigForUser($this->user)['widgets'], 'id'),
            array_column($response->json('config.widgets'), 'id'),
        );
        $this->assertDatabaseHas('dashboard_layouts', [
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'is_home' => true,
        ]);
    }

    #[Test]
    public function reset_layout_pins_home_essential_kpi_formulas_only(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $saldoTemplate = FormulaWidget::query()
            ->where('template_slug', 'official.saldo_liquidita')
            ->where('is_official_template', true)
            ->firstOrFail();

        $saldoClone = $this->createUserFormulaWidget([
            'source_id' => $saldoTemplate->id,
            'default_size' => 'sm',
        ]);
        $extra = $this->createUserFormulaWidget(['default_size' => 'lg']);

        $board = $this->editableBoard([
            'widgets' => [
                ['id' => "formula_widget_{$extra->id}", 'visible' => true, 'position' => 0, 'size' => 'lg'],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('dashboard.layout.reset', ['board' => $board->id]));

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'active_budgets']);
        $response->assertJsonFragment(['id' => 'expense_treemap']);
        $response->assertJsonFragment(['id' => "formula_widget_{$saldoClone->id}"]);
        $response->assertJsonMissing(['id' => "formula_widget_{$extra->id}"]);

        $board->refresh();
        $this->assertSame(
            array_column(DashboardLayout::essentialConfigForUser($this->user)['widgets'], 'id'),
            array_column($board->config['widgets'], 'id'),
        );
    }

    #[Test]
    public function home_dashboard_does_not_auto_merge_non_essential_formula_widgets(): void
    {
        $this->seed(FormulaWidgetTemplateSeeder::class);

        $cashflowTemplate = FormulaWidget::query()
            ->where('template_slug', 'official.cashflow_mensile')
            ->where('is_official_template', true)
            ->firstOrFail();

        $cashflowClone = $this->createUserFormulaWidget([
            'source_id' => $cashflowTemplate->id,
            'default_size' => 'md',
        ]);

        DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfig(),
        ]);

        $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardLayout.widgets', function ($widgets) use ($cashflowClone): bool {
                    $ids = collect($widgets)->pluck('id')->all();

                    return $ids === array_column(DashboardLayout::essentialConfigForUser($this->user)['widgets'], 'id')
                        && ! in_array("formula_widget_{$cashflowClone->id}", $ids, true);
                })
            );
    }

    #[Test]
    public function custom_board_does_not_auto_merge_installed_formula_widgets(): void
    {
        $widget = $this->createUserFormulaWidget(['default_size' => 'md']);

        $board = $this->editableBoard(DashboardLayout::defaultConfig());

        $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('dashboard', ['board' => $board->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dashboardLayout.widgets', function ($widgets) use ($widget): bool {
                    $ids = collect($widgets)->pluck('id')->all();

                    return ! in_array("formula_widget_{$widget->id}", $ids, true);
                })
            );

        $board->refresh();
        $ids = array_column($board->config['widgets'] ?? [], 'id');
        $this->assertNotContains("formula_widget_{$widget->id}", $ids);
    }

    #[Test]
    public function user_cannot_access_another_users_layout(): void
    {
        $otherUser = User::factory()->create();
        $otherHousehold = Household::factory()->create(['owner_user_id' => $otherUser->id]);
        $otherHousehold->users()->attach($otherUser->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $otherUser->update(['active_household_id' => $otherHousehold->id]);

        DashboardLayout::create([
            'user_id' => $otherUser->id,
            'household_id' => $otherHousehold->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => ['widgets' => [
                ['id' => 'accounts', 'visible' => true, 'position' => 0, 'size' => 'lg'],
            ]],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.layout.show'));

        $response->assertOk();
        $widgets = $response->json('config.widgets');
        $this->assertSame(
            ['active_budgets', 'expense_treemap', 'recent_transactions', 'accounts'],
            array_column($widgets, 'id'),
        );
    }

    // ─── Dashboard Inertia integration ────────────────────────────────────

    #[Test]
    public function dashboard_page_receives_layout_config(): void
    {
        $response = $this->withoutVite()->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $expectedIds = array_column(DashboardLayout::essentialConfigForUser($this->user)['widgets'], 'id');
        $response->assertInertia(fn ($page) => $page->has('dashboardLayout')
            ->has('dashboardLayout.widgets')
            ->where('canEditLayout', true)
            ->where('dashboardLayout.widgets', function ($widgets) use ($expectedIds): bool {
                return collect($widgets)->pluck('id')->all() === $expectedIds;
            })
        );
    }

    #[Test]
    public function dashboard_page_receives_saved_layout(): void
    {
        $config = [
            'widgets' => [
                ['id' => 'accounts',             'visible' => true, 'position' => 0, 'size' => 'lg'],
                ['id' => 'lifestyle_widget',     'visible' => true, 'position' => 1, 'size' => 'lg'],
                ['id' => 'recent_transactions',  'visible' => true, 'position' => 2, 'size' => 'md'],
                ['id' => 'active_budgets',       'visible' => true, 'position' => 3, 'size' => 'md'],
                ['id' => 'debts_credits',        'visible' => true, 'position' => 4, 'size' => 'md'],
            ],
        ];

        $board = $this->editableBoard($config);

        $response = $this->withoutVite()->actingAs($this->user)
            ->get(route('dashboard', ['board' => $board->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->where('dashboardLayout.widgets', function ($widgets): bool {
            return collect($widgets)->pluck('id')->contains('accounts')
                && collect($widgets)->pluck('id')->contains('lifestyle_widget');
        }));
    }

    // ─── Regression: financial_goals must be accepted ─────────────────────

    #[Test]
    public function saving_layout_with_financial_goals_widget_succeeds(): void
    {
        $board = $this->editableBoard();

        $payload = [
            'board' => $board->id,
            'config' => [
                'widgets' => [
                    ['id' => 'accounts',         'visible' => true, 'position' => 0, 'size' => 'xl'],
                    ['id' => 'financial_goals',  'visible' => true, 'position' => 1, 'size' => 'md'],
                ],
            ],
        ];

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), $payload)
            ->assertOk();
    }

    #[Test]
    public function saving_full_default_layout_succeeds(): void
    {
        $board = $this->editableBoard();
        $payload = [
            'board' => $board->id,
            'config' => DashboardLayout::defaultConfig(),
        ];

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), $payload)
            ->assertOk();
    }

    #[Test]
    public function all_allowed_widget_ids_are_accepted_by_validation(): void
    {
        $board = $this->editableBoard();

        $allWidgets = [
            'lifestyle_widget', 'accounts',
            'recent_transactions', 'active_budgets', 'debts_credits',
            'asset_allocation', 'expense_treemap', 'financial_goals', 'expense_distribution', 'pac_projection',
        ];

        $widgets = array_values(array_map(fn ($id, $pos) => [
            'id' => $id, 'visible' => true, 'position' => $pos, 'size' => 'md',
        ], $allWidgets, array_keys($allWidgets)));

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), [
                'board' => $board->id,
                'config' => ['widgets' => $widgets],
            ])
            ->assertOk();
    }

    #[Test]
    public function tier_a_legacy_widget_ids_are_stripped_on_save(): void
    {
        $board = $this->editableBoard();

        foreach (DashboardLayout::TIER_A_LEGACY_WIDGET_IDS as $legacyId) {
            $this->actingAs($this->user)
                ->postJson(route('dashboard.layout.store'), [
                    'board' => $board->id,
                    'config' => [
                        'widgets' => [
                            ['id' => 'accounts', 'visible' => true, 'position' => 0, 'size' => 'md'],
                            ['id' => $legacyId, 'visible' => true, 'position' => 1, 'size' => 'md'],
                        ],
                    ],
                ])
                ->assertOk();

            $ids = array_column($board->fresh()->config['widgets'], 'id');
            $this->assertSame(['accounts'], $ids);
        }
    }

    #[Test]
    public function dashboard_page_strips_legacy_widgets_from_saved_layout(): void
    {
        $board = $this->editableBoard([
            'widgets' => [
                ['id' => 'total_balance', 'visible' => true, 'position' => 0, 'size' => 'xl'],
                ['id' => 'accounts', 'visible' => true, 'position' => 1, 'size' => 'md'],
            ],
        ]);

        $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('dashboard', ['board' => $board->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('dashboardLayout.widgets')
                ->where('dashboardLayout.widgets', function ($widgets): bool {
                    $ids = collect($widgets)->pluck('id')->all();

                    return ! in_array('total_balance', $ids, true) && in_array('accounts', $ids, true);
                })
            );
    }

    #[Test]
    public function saving_layout_strips_orphan_formula_widgets(): void
    {
        $board = $this->editableBoard();
        $owned = $this->createUserFormulaWidget();

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), [
                'board' => $board->id,
                'config' => [
                    'widgets' => [
                        ['id' => 'accounts', 'visible' => true, 'position' => 0, 'size' => 'md'],
                        ['id' => "formula_widget_{$owned->id}", 'visible' => true, 'position' => 1, 'size' => 'md'],
                        ['id' => 'formula_widget_99999', 'visible' => true, 'position' => 2, 'size' => 'md'],
                    ],
                ],
            ])
            ->assertOk();

        $layout = $board->fresh();
        $ids = array_column($layout->config['widgets'], 'id');

        $this->assertContains('accounts', $ids);
        $this->assertContains("formula_widget_{$owned->id}", $ids);
        $this->assertNotContains('formula_widget_99999', $ids);
    }

    #[Test]
    public function saving_layout_remaps_official_template_formula_widget_id_to_user_clone(): void
    {
        $board = $this->editableBoard();
        $official = FormulaWidget::factory()
            ->officialTemplate('official.test_widget')
            ->create();

        $clone = $this->createUserFormulaWidget(['source_id' => $official->id]);

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), [
                'board' => $board->id,
                'config' => [
                    'widgets' => [
                        ['id' => "formula_widget_{$official->id}", 'visible' => true, 'position' => 0, 'size' => 'lg'],
                    ],
                ],
            ])
            ->assertOk();

        $layout = $board->fresh();
        $ids = array_column($layout->config['widgets'], 'id');

        $this->assertSame(["formula_widget_{$clone->id}"], $ids);
    }
}
