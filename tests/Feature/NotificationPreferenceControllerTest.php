<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationPreferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    #[Test]
    public function user_can_save_upcoming_due_frequency(): void
    {
        $user = $this->createUserWithActiveHousehold();

        $response = $this->actingAs($user)->patchJson(route('user.preferences.notifications'), [
            'upcoming_due_dates' => [
                'frequency' => 'weekly',
                'channels' => ['in_app'],
            ],
            'monthly_spending' => [
                'enabled' => true,
                'channels' => ['in_app'],
            ],
            'educational_suggestions' => [
                'enabled' => true,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('upcoming_due_dates.frequency', 'weekly');

        $user->refresh();

        $this->assertSame(
            'weekly',
            data_get($user->preferences, 'notifications.upcoming_due_dates.frequency'),
        );
        $this->assertFalse(
            data_get($user->preferences, 'notifications.recurring_reminder.enabled'),
        );
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

        return $user->fresh();
    }
}
