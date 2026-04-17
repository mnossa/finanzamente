<?php

namespace Tests\Feature;

use App\Models\DashboardLayout;
use App\Models\Household;
use App\Models\User;
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
        $response->assertJsonFragment(['id' => 'total_balance']);
    }

    #[Test]
    public function authenticated_user_receives_saved_layout(): void
    {
        $config = [
            'widgets' => [
                ['id' => 'quick_actions',       'visible' => true,  'position' => 0, 'size' => 'lg'],
                ['id' => 'total_balance',        'visible' => false, 'position' => 1, 'size' => 'lg'],
                ['id' => 'monthly_stats',        'visible' => true,  'position' => 2, 'size' => 'lg'],
                ['id' => 'annual_revenue',       'visible' => true,  'position' => 3, 'size' => 'lg'],
                ['id' => 'tax_thermometer',      'visible' => true,  'position' => 4, 'size' => 'lg'],
                ['id' => 'lifestyle_widget',     'visible' => true,  'position' => 5, 'size' => 'lg'],
                ['id' => 'accounts',             'visible' => true,  'position' => 6, 'size' => 'md'],
                ['id' => 'recent_transactions',  'visible' => true,  'position' => 7, 'size' => 'md'],
                ['id' => 'active_budgets',       'visible' => true,  'position' => 8, 'size' => 'md'],
                ['id' => 'debts_credits',        'visible' => true,  'position' => 9, 'size' => 'md'],
            ],
        ];

        DashboardLayout::create([
            'user_id'      => $this->user->id,
            'household_id' => $this->household->id,
            'config'       => $config,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.layout.show'));

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'quick_actions']);
        // Verifica che total_balance sia nascosto (visible=false)
        $widgets = $response->json('config.widgets');
        $totalBalance = collect($widgets)->firstWhere('id', 'total_balance');
        $this->assertFalse($totalBalance['visible']);
    }

    // ─── POST /dashboard/layout ────────────────────────────────────────────

    #[Test]
    public function user_can_save_valid_layout(): void
    {
        $payload = [
            'config' => [
                'widgets' => [
                    ['id' => 'total_balance',       'visible' => true, 'position' => 0, 'size' => 'lg'],
                    ['id' => 'monthly_stats',        'visible' => true, 'position' => 1, 'size' => 'lg'],
                    ['id' => 'annual_revenue',       'visible' => true, 'position' => 2, 'size' => 'lg'],
                    ['id' => 'tax_thermometer',      'visible' => true, 'position' => 3, 'size' => 'lg'],
                    ['id' => 'lifestyle_widget',     'visible' => true, 'position' => 4, 'size' => 'lg'],
                    ['id' => 'accounts',             'visible' => true, 'position' => 5, 'size' => 'md'],
                    ['id' => 'recent_transactions',  'visible' => true, 'position' => 6, 'size' => 'md'],
                    ['id' => 'active_budgets',       'visible' => true, 'position' => 7, 'size' => 'md'],
                    ['id' => 'debts_credits',        'visible' => true, 'position' => 8, 'size' => 'md'],
                    ['id' => 'quick_actions',        'visible' => true, 'position' => 9, 'size' => 'lg'],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), $payload);

        $response->assertOk();
        $response->assertJsonStructure(['config', 'message']);

        $this->assertDatabaseHas('dashboard_layouts', [
            'user_id'      => $this->user->id,
            'household_id' => $this->household->id,
        ]);
    }

    #[Test]
    public function saving_layout_updates_existing_record(): void
    {
        // Primo salvataggio
        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), [
                'config' => [
                    'widgets' => [
                        ['id' => 'total_balance',       'visible' => true, 'position' => 0, 'size' => 'lg'],
                        ['id' => 'monthly_stats',        'visible' => true, 'position' => 1, 'size' => 'lg'],
                        ['id' => 'annual_revenue',       'visible' => true, 'position' => 2, 'size' => 'lg'],
                        ['id' => 'tax_thermometer',      'visible' => true, 'position' => 3, 'size' => 'lg'],
                        ['id' => 'lifestyle_widget',     'visible' => true, 'position' => 4, 'size' => 'lg'],
                        ['id' => 'accounts',             'visible' => true, 'position' => 5, 'size' => 'md'],
                        ['id' => 'recent_transactions',  'visible' => true, 'position' => 6, 'size' => 'md'],
                        ['id' => 'active_budgets',       'visible' => true, 'position' => 7, 'size' => 'md'],
                        ['id' => 'debts_credits',        'visible' => true, 'position' => 8, 'size' => 'md'],
                        ['id' => 'quick_actions',        'visible' => true, 'position' => 9, 'size' => 'lg'],
                    ],
                ],
            ]);

        // Secondo salvataggio (aggiornamento)
        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), [
                'config' => [
                    'widgets' => [
                        ['id' => 'quick_actions',        'visible' => true,  'position' => 0, 'size' => 'lg'],
                        ['id' => 'total_balance',        'visible' => false, 'position' => 1, 'size' => 'lg'],
                        ['id' => 'monthly_stats',        'visible' => true,  'position' => 2, 'size' => 'lg'],
                        ['id' => 'annual_revenue',       'visible' => true,  'position' => 3, 'size' => 'lg'],
                        ['id' => 'tax_thermometer',      'visible' => true,  'position' => 4, 'size' => 'lg'],
                        ['id' => 'lifestyle_widget',     'visible' => true,  'position' => 5, 'size' => 'lg'],
                        ['id' => 'accounts',             'visible' => true,  'position' => 6, 'size' => 'md'],
                        ['id' => 'recent_transactions',  'visible' => true,  'position' => 7, 'size' => 'md'],
                        ['id' => 'active_budgets',       'visible' => true,  'position' => 8, 'size' => 'md'],
                        ['id' => 'debts_credits',        'visible' => true,  'position' => 9, 'size' => 'md'],
                    ],
                ],
            ]);

        // Deve esistere un solo record per utente/household
        $this->assertCount(
            1,
            DashboardLayout::where('user_id', $this->user->id)
                ->where('household_id', $this->household->id)
                ->get()
        );

        // Verifica che la configurazione sia aggiornata
        $layout = DashboardLayout::where('user_id', $this->user->id)->first();
        $quickActions = collect($layout->config['widgets'])->firstWhere('id', 'quick_actions');
        $this->assertEquals(0, $quickActions['position']);
    }

    #[Test]
    public function saving_layout_with_invalid_widget_id_fails(): void
    {
        $payload = [
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
        $payload = [
            'config' => [
                'widgets' => [
                    ['id' => 'total_balance', 'visible' => true, 'position' => 0, 'size' => 'xxl'],
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
        $response = $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), []);

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
        DashboardLayout::create([
            'user_id'      => $this->user->id,
            'household_id' => $this->household->id,
            'config'       => ['widgets' => []],
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('dashboard.layout.reset'));

        $response->assertOk();
        $response->assertJsonFragment(['id' => 'total_balance']);

        $this->assertDatabaseMissing('dashboard_layouts', [
            'user_id'      => $this->user->id,
            'household_id' => $this->household->id,
        ]);
    }

    #[Test]
    public function resetting_layout_with_no_saved_layout_returns_default(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson(route('dashboard.layout.reset'));

        $response->assertOk();
        $response->assertJsonStructure(['config' => ['widgets']]);
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

        // Salva un layout per l'altro utente
        DashboardLayout::create([
            'user_id'      => $otherUser->id,
            'household_id' => $otherHousehold->id,
            'config'       => ['widgets' => [
                ['id' => 'quick_actions', 'visible' => true, 'position' => 0, 'size' => 'lg'],
            ]],
        ]);

        // L'utente corrente non vede il layout dell'altro utente
        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.layout.show'));

        $response->assertOk();
        // Deve ricevere il layout di default, non quello dell'altro utente
        $widgets = $response->json('config.widgets');
        // Il default ha più widget del layout dell'altro utente
        $this->assertGreaterThan(1, count($widgets));
    }

    // ─── Dashboard Inertia integration ────────────────────────────────────

    #[Test]
    public function dashboard_page_receives_layout_config(): void
    {
        $response = $this->withoutVite()->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('dashboardLayout')
                ->has('dashboardLayout.widgets')
        );
    }

    #[Test]
    public function dashboard_page_receives_saved_layout(): void
    {
        $config = [
            'widgets' => [
                ['id' => 'quick_actions',       'visible' => true, 'position' => 0, 'size' => 'lg'],
                ['id' => 'total_balance',        'visible' => true, 'position' => 1, 'size' => 'lg'],
                ['id' => 'monthly_stats',        'visible' => true, 'position' => 2, 'size' => 'lg'],
                ['id' => 'annual_revenue',       'visible' => true, 'position' => 3, 'size' => 'lg'],
                ['id' => 'tax_thermometer',      'visible' => true, 'position' => 4, 'size' => 'lg'],
                ['id' => 'lifestyle_widget',     'visible' => true, 'position' => 5, 'size' => 'lg'],
                ['id' => 'accounts',             'visible' => true, 'position' => 6, 'size' => 'md'],
                ['id' => 'recent_transactions',  'visible' => true, 'position' => 7, 'size' => 'md'],
                ['id' => 'active_budgets',       'visible' => true, 'position' => 8, 'size' => 'md'],
                ['id' => 'debts_credits',        'visible' => true, 'position' => 9, 'size' => 'md'],
            ],
        ];

        DashboardLayout::create([
            'user_id'      => $this->user->id,
            'household_id' => $this->household->id,
            'config'       => $config,
        ]);

        $response = $this->withoutVite()->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->where('dashboardLayout.widgets.0.id', 'quick_actions')
        );
    }

    // ─── Regressione: financial_goals non deve essere rifiutato ───────────

    #[Test]
    public function saving_layout_with_financial_goals_widget_succeeds(): void
    {
        $payload = [
            'config' => [
                'widgets' => [
                    ['id' => 'total_balance',    'visible' => true, 'position' => 0, 'size' => 'xl'],
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
        // Tutti i widget del defaultConfig() devono essere accettati senza errori
        $payload = ['config' => DashboardLayout::defaultConfig()];

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), $payload)
            ->assertOk();
    }

    #[Test]
    public function all_allowed_widget_ids_are_accepted_by_validation(): void
    {
        $allWidgets = [
            'total_balance', 'monthly_stats', 'annual_revenue', 'tax_thermometer',
            'lifestyle_widget', 'accounts', 'recent_transactions', 'active_budgets',
            'debts_credits', 'quick_actions', 'asset_allocation', 'net_worth',
            'cash_flow', 'expense_treemap', 'financial_goals',
        ];

        $widgets = array_values(array_map(fn ($id, $pos) => [
            'id' => $id, 'visible' => true, 'position' => $pos, 'size' => 'md',
        ], $allWidgets, array_keys($allWidgets)));

        $this->actingAs($this->user)
            ->postJson(route('dashboard.layout.store'), ['config' => ['widgets' => $widgets]])
            ->assertOk();
    }
}
