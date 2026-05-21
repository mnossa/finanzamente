<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\SavedSimulationScenario;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SimulationScenarioTest extends TestCase
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
    public function guest_cannot_store_simulation_scenario(): void
    {
        $this->post(route('simulation-scenarios.store'), [
            'name' => 'Test',
            'tab' => 'compound',
            'payload' => ['initialCapital' => 5000],
        ])->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_user_can_save_and_reload_scenario(): void
    {
        $this->actingAs($this->user)
            ->post(route('simulation-scenarios.store'), [
                'name' => 'Piano pensione',
                'tab' => 'compound',
                'payload' => [
                    'initialCapital' => 15000,
                    'monthlyContribution' => 400,
                    'annualReturn' => 6,
                    'years' => 25,
                    'inflationEnabled' => true,
                    'inflationRate' => 2,
                ],
            ])
            ->assertRedirect(route('simulations.public'));

        $this->assertDatabaseHas('saved_simulation_scenarios', [
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'Piano pensione',
            'tab' => 'compound',
        ]);

        $scenario = SavedSimulationScenario::first();
        $this->assertSame(15000, (int) $scenario->payload['initialCapital']);

        $this->withoutVite()
            ->get(route('simulations.public'))
            ->assertInertia(fn ($page) => $page
                ->component('Simulations/Index')
                ->where('canSave', true)
                ->has('savedScenarios', 1)
                ->where('savedScenarios.0.name', 'Piano pensione')
            );
    }

    #[Test]
    public function scenario_name_must_be_unique_per_user_and_household(): void
    {
        SavedSimulationScenario::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'Duplicato',
            'tab' => 'emergency',
            'payload' => ['monthlyExpenses' => 1000],
        ]);

        $this->actingAs($this->user)
            ->from(route('simulations.public'))
            ->post(route('simulation-scenarios.store'), [
                'name' => 'Duplicato',
                'tab' => 'compound',
                'payload' => ['initialCapital' => 1000],
            ])
            ->assertRedirect(route('simulations.public'))
            ->assertSessionHasErrors('name');
    }
}
