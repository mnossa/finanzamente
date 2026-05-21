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
    public function guest_can_access_public_simulations_page(): void
    {
        $this->withoutVite()
            ->get(route('simulations.public'))
            ->assertOk();
    }

    #[Test]
    public function authenticated_user_sees_authenticated_simulations_page(): void
    {
        $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('simulations.public'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Simulations/Index')
                ->where('canSave', true)
                ->has('savedScenarios')
            );
    }

    #[Test]
    public function simulations_page_renders_correct_inertia_component(): void
    {
        $response = $this->withoutVite()
            ->get(route('simulations.public'));

        $response->assertInertia(fn ($page) => $page->component('Simulations/PublicIndex')
            ->has('presetScenarios')
            ->has('historicalData')
            ->has('crisisScenarios')
            ->where('canRegister', true)
        );
    }

    #[Test]
    public function simulations_page_returns_three_preset_scenarios(): void
    {
        $response = $this->withoutVite()
            ->get(route('simulations.public'));

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
            ->get(route('simulations.public'));

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
            ->get(route('simulations.public'));

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
            ->get(route('simulations.public'));

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
            ->get(route('simulations.public'));

        $response->assertInertia(fn ($page) => $page->has('presetScenarios.0.id')
            ->has('presetScenarios.0.name')
            ->has('presetScenarios.0.return')
            ->has('presetScenarios.0.description')
        );
    }

    #[Test]
    public function simulations_public_route_has_correct_name(): void
    {
        $this->assertTrue(
            Route::has('simulations.public'),
            'La rotta simulations.public deve esistere'
        );
    }

    #[Test]
    public function simulations_page_hides_register_cta_in_prelaunch_mode(): void
    {
        config([
            'prelaunch.enabled' => true,
            'prelaunch.owner_email' => 'owner@example.com',
        ]);

        $this->withoutVite()
            ->get(route('simulations.public'))
            ->assertInertia(fn ($page) => $page
                ->component('Simulations/PublicIndex')
                ->where('canRegister', false)
            );
    }
}
