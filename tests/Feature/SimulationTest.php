<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SimulationTest extends TestCase
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
    public function unauthenticated_user_cannot_access_simulations(): void
    {
        $this->get(route('simulations.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_user_can_access_simulations_page(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('simulations.index'));

        $response->assertStatus(200);
    }

    #[Test]
    public function simulations_page_renders_correct_inertia_component(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('simulations.index'));

        $response->assertInertia(fn ($page) => $page->component('Simulations/Index')
            ->has('presetScenarios')
            ->has('historicalData')
            ->has('crisisScenarios')
        );
    }

    #[Test]
    public function simulations_page_returns_three_preset_scenarios(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('simulations.index'));

        $response->assertInertia(fn ($page) => $page->has('presetScenarios', 3)
            ->where('presetScenarios.0.id', 'conservative')
            ->where('presetScenarios.1.id', 'moderate')
            ->where('presetScenarios.2.id', 'aggressive')
        );
    }

    #[Test]
    public function simulations_page_returns_historical_data(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('simulations.index'));

        $response->assertInertia(fn ($page) => $page->has('historicalData.sp500_avg_return')
            ->has('historicalData.avg_inflation_italy')
            ->has('historicalData.avg_bond_return')
            ->has('historicalData.avg_savings_account')
        );
    }

    #[Test]
    public function simulations_page_returns_three_crisis_scenarios(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('simulations.index'));

        $response->assertInertia(fn ($page) => $page->has('crisisScenarios', 3)
            ->where('crisisScenarios.0.id', 'crisis_2008')
            ->where('crisisScenarios.1.id', 'covid_2020')
            ->where('crisisScenarios.2.id', 'dot_com')
        );
    }

    #[Test]
    public function crisis_scenarios_have_required_fields(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('simulations.index'));

        $response->assertInertia(fn ($page) => $page->has('crisisScenarios.0.id')
            ->has('crisisScenarios.0.name')
            ->has('crisisScenarios.0.description')
            ->has('crisisScenarios.0.peak_drop')
            ->has('crisisScenarios.0.recovery_months')
            ->has('crisisScenarios.0.monthly_returns')
            ->has('crisisScenarios.0.labels')
        );
    }

    #[Test]
    public function preset_scenarios_have_required_fields(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('simulations.index'));

        $response->assertInertia(fn ($page) => $page->has('presetScenarios.0.id')
            ->has('presetScenarios.0.name')
            ->has('presetScenarios.0.return')
            ->has('presetScenarios.0.description')
        );
    }

    #[Test]
    public function simulations_route_has_correct_name(): void
    {
        $this->assertTrue(
            Route::has('simulations.index'),
            'La rotta simulations.index deve esistere'
        );
    }
}
