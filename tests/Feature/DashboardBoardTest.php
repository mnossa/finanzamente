<?php

namespace Tests\Feature;

use App\Models\DashboardLayout;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardBoardTest extends TestCase
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
    public function index_creates_home_when_missing_and_lists_boards(): void
    {
        $this->actingAs($this->user)
            ->get(route('dashboard.boards.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Boards')
                ->has('boards', 1)
                ->where('boards.0.name', 'Home')
                ->where('boards.0.is_home', true)
                ->where('boardLimit', 10)
                ->where('canCreate', true)
            );

        $this->assertDatabaseHas('dashboard_layouts', [
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
        ]);
    }

    #[Test]
    public function empty_template_board_has_no_widgets_on_create_and_load(): void
    {
        DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfigForUser($this->user),
        ]);

        $this->actingAs($this->user)
            ->post(route('dashboard.boards.store'), [
                'name' => 'Vuota test',
                'template' => 'empty',
            ])
            ->assertRedirect(route('dashboard.boards.index'));

        $board = DashboardLayout::query()
            ->where('user_id', $this->user->id)
            ->where('name', 'Vuota test')
            ->firstOrFail();

        $this->assertSame([], $board->config['widgets'] ?? null);

        $this->actingAs($this->user)
            ->get(route('dashboard', ['board' => $board->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('dashboardLayout.widgets', [])
            );

        $this->actingAs($this->user)
            ->getJson(route('dashboard.layout.show', ['board' => $board->id]))
            ->assertOk()
            ->assertJsonPath('config.widgets', []);

        $board->refresh();
        $this->assertSame([], $board->config['widgets'] ?? null);
    }

    #[Test]
    public function user_can_create_boards_up_to_limit(): void
    {
        DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfigForUser($this->user),
        ]);

        $this->actingAs($this->user)
            ->post(route('dashboard.boards.store'), [
                'name' => 'Risparmi',
                'template' => 'essential',
            ])
            ->assertRedirect(route('dashboard.boards.index'));

        $this->assertDatabaseHas('dashboard_layouts', [
            'user_id' => $this->user->id,
            'name' => 'Risparmi',
            'is_home' => false,
        ]);

        for ($i = 2; $i < 10; $i++) {
            DashboardLayout::create([
                'user_id' => $this->user->id,
                'household_id' => $this->household->id,
                'name' => "Board {$i}",
                'is_home' => false,
                'sort_order' => $i,
                'config' => ['widgets' => []],
            ]);
        }

        $this->actingAs($this->user)
            ->post(route('dashboard.boards.store'), [
                'name' => 'Extra',
                'template' => 'empty',
            ])
            ->assertSessionHasErrors('name');
    }

    #[Test]
    public function user_cannot_delete_home_but_can_switch_home(): void
    {
        $home = DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => ['widgets' => []],
        ]);

        $other = DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Analisi',
            'is_home' => false,
            'sort_order' => 1,
            'config' => ['widgets' => []],
        ]);

        $this->actingAs($this->user)
            ->delete(route('dashboard.boards.destroy', $home))
            ->assertSessionHasErrors('board');

        $this->assertDatabaseHas('dashboard_layouts', ['id' => $home->id, 'is_home' => true]);

        $this->actingAs($this->user)
            ->post(route('dashboard.boards.set-home', $other))
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($home->fresh()->is_home);
        $this->assertTrue($other->fresh()->is_home);
    }

    #[Test]
    public function layout_show_targets_home_board(): void
    {
        DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => [
                'widgets' => [
                    ['id' => 'accounts', 'visible' => true, 'position' => 0, 'size' => 'md'],
                ],
            ],
        ]);

        DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Altro',
            'is_home' => false,
            'sort_order' => 1,
            'config' => [
                'widgets' => [
                    ['id' => 'financial_goals', 'visible' => true, 'position' => 0, 'size' => 'md'],
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('dashboard.layout.show'));

        $response->assertOk();
        $ids = array_column($response->json('config.widgets'), 'id');
        $this->assertSame(['accounts'], $ids);
        $this->assertTrue($response->json('canEditLayout'));
        $this->assertTrue($response->json('board.is_home'));
    }

    #[Test]
    public function user_has_board_limit_of_ten(): void
    {
        DashboardLayout::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => ['widgets' => []],
        ]);

        $this->actingAs($this->user)
            ->get(route('dashboard.boards.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('boardLimit', 10));
    }
}
