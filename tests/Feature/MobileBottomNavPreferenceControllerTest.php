<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MobileBottomNavPreferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    #[Test]
    public function user_can_save_mobile_bottom_nav_slots(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this->actingAs($user)->patchJson(route('user.preferences.mobile_bottom_nav'), [
            'mobile_bottom_nav' => ['dashboard', 'planning', 'investments'],
        ]);

        $response->assertOk()
            ->assertJsonPath('mobile_bottom_nav', ['dashboard', 'planning', 'investments']);

        $user->refresh();

        $this->assertSame(
            ['dashboard', 'planning', 'investments'],
            data_get($user->preferences, 'mobile_bottom_nav'),
        );
    }

    #[Test]
    public function mobile_bottom_nav_rejects_duplicate_slots(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this->actingAs($user)->patchJson(route('user.preferences.mobile_bottom_nav'), [
            'mobile_bottom_nav' => ['dashboard', 'dashboard', 'organization'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile_bottom_nav.1']);
    }

    #[Test]
    public function mobile_bottom_nav_rejects_unknown_destination(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this->actingAs($user)->patchJson(route('user.preferences.mobile_bottom_nav'), [
            'mobile_bottom_nav' => ['dashboard', 'cashflow', 'unknown'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile_bottom_nav.2']);
    }

    private function createUserWithActiveHousehold(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $household = Household::create([
            'name' => 'Test Household',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'supervise' => true]),
        ]);

        $user->update(['active_household_id' => $household->id]);

        return $user;
    }
}
