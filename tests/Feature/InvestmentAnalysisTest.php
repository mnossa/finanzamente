<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\InvestmentAnalysis;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentAnalysisTest extends TestCase
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
        $this->household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function authenticated_user_can_view_investment_analyses_index(): void
    {
        $response = $this->actingAs($this->user)->get(route('investment-analyses.index'));

        $response->assertStatus(200);
    }

    #[Test]
    public function authenticated_user_can_create_investment_analysis(): void
    {
        $response = $this->actingAs($this->user)->post(route('investment-analyses.store'), [
            'name' => 'Impianto Fotovoltaico Casa',
            'template_type' => 'fotovoltaico',
            'start_date' => '2026-01-01',
            'initial_cost' => 8000,
            'total_annual_savings' => 1200,
            'breakeven_years' => 6.67,
            'roi_percentage' => 15,
        ]);

        $response->assertRedirect(route('investment-analyses.index'));
        $this->assertDatabaseHas('investment_analyses', [
            'name' => 'Impianto Fotovoltaico Casa',
            'template_type' => 'fotovoltaico',
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function store_requires_name_and_template_type(): void
    {
        $response = $this->actingAs($this->user)->post(route('investment-analyses.store'), [
            'initial_cost' => 5000,
        ]);

        $response->assertSessionHasErrors(['name', 'template_type']);
    }

    #[Test]
    public function store_rejects_invalid_template_type(): void
    {
        $response = $this->actingAs($this->user)->post(route('investment-analyses.store'), [
            'name' => 'Test',
            'template_type' => 'tipo_inesistente',
            'initial_cost' => 5000,
        ]);

        $response->assertSessionHasErrors(['template_type']);
    }

    #[Test]
    public function authenticated_user_can_delete_their_investment_analysis(): void
    {
        $analysis = InvestmentAnalysis::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'name' => 'Test Analysis',
            'template_type' => 'personalizzato',
            'initial_cost' => 5000,
        ]);

        $response = $this->actingAs($this->user)->delete(route('investment-analyses.destroy', $analysis));

        $response->assertRedirect(route('investment-analyses.index'));
        $this->assertSoftDeleted('investment_analyses', ['id' => $analysis->id]);
    }

    #[Test]
    public function user_cannot_delete_analysis_from_another_household(): void
    {
        $otherUser = User::factory()->create();
        $otherHousehold = Household::factory()->create(['owner_user_id' => $otherUser->id]);
        $otherHousehold->users()->attach($otherUser->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $otherUser->update(['active_household_id' => $otherHousehold->id]);

        $analysis = InvestmentAnalysis::create([
            'user_id' => $otherUser->id,
            'household_id' => $otherHousehold->id,
            'name' => 'Altra analisi',
            'template_type' => 'personalizzato',
            'initial_cost' => 3000,
        ]);

        $response = $this->actingAs($this->user)->delete(route('investment-analyses.destroy', $analysis));

        $response->assertStatus(403);
        $this->assertDatabaseHas('investment_analyses', ['id' => $analysis->id, 'deleted_at' => null]);
    }
}
