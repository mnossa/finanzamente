<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateCohortFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_profile_accepts_valid_cohort_fields(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'income_band' => '35k_50k',
            'macro_region' => 'centro',
        ])->assertRedirect(route('profile.edit'));

        $this->assertSame('35k_50k', $user->fresh()->income_band);
        $this->assertSame('centro', $user->fresh()->macro_region);
    }

    public function test_profile_rejects_invalid_income_band(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'income_band' => 'invalid_band',
        ])->assertSessionHasErrors('income_band');
    }

    private function createUserWithActiveHousehold(): User
    {
        $user = User::factory()->create(['profile_completed' => true]);

        $household = Household::create([
            'name' => 'HH Profilo',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'supervise' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        return $user->fresh();
    }
}
