<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Currency;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RevenueNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    // ───────────────────────────────────────────────────
    // Helper
    // ───────────────────────────────────────────────────

    private function createVatUserWithHousehold(array $profileSettings = []): User
    {
        $user = User::factory()->create([
            'profile_settings' => array_merge([
                'has_vat' => true,
                'revenue_threshold' => 85000,
                'revenue_tracking_enabled' => true,
                'family_status' => 'single',
                'tracks_investments' => false,
            ], $profileSettings),
            'profile_completed' => true,
        ]);

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

    private function createTransactionForUser(User $user, float $amount, ?string $date = null): void
    {
        $currency = Currency::where('code', 'EUR')->first();

        $account = Account::create([
            'household_id' => $user->active_household_id,
            'owner_user_id' => $user->id,
            'name' => 'Conto Test',
            'type' => 'checking',
            'currency_code' => 'EUR',
            'initial_balance' => 0,
            'active' => true,
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => $amount,
            'currency_code' => 'EUR',
            'date' => $date ?? Carbon::now()->toDateString(),
            'description' => 'Test income',
            'is_private' => false,
        ]);
    }

    // ───────────────────────────────────────────────────
    // ProfileQuizController tests
    // ───────────────────────────────────────────────────

    public function test_revenue_settings_can_be_saved_via_quiz_update(): void
    {
        $user = $this->createVatUserWithHousehold();

        $response = $this->actingAs($user)->patch(route('profile.quiz-settings.update'), [
            'has_vat' => true,
            'family_status' => 'single',
            'tracks_investments' => false,
            'revenue_threshold' => 100000,
            'revenue_tracking_enabled' => true,
        ]);

        $response->assertRedirect(route('profile.edit'));

        $user->refresh();
        $settings = $user->profile_settings;
        $this->assertSame(100000.0, (float) $settings['revenue_threshold']);
        $this->assertTrue($settings['revenue_tracking_enabled']);
    }

    public function test_revenue_tracking_can_be_disabled_via_toggle(): void
    {
        $user = $this->createVatUserWithHousehold(['revenue_tracking_enabled' => true]);

        $response = $this->actingAs($user)->post(route('profile.revenue-tracking.toggle'));

        $response->assertRedirect();
        $user->refresh();
        $this->assertFalse($user->profile_settings['revenue_tracking_enabled']);
    }

    public function test_revenue_tracking_can_be_re_enabled_via_toggle(): void
    {
        $user = $this->createVatUserWithHousehold(['revenue_tracking_enabled' => false]);

        $this->actingAs($user)->post(route('profile.revenue-tracking.toggle'));

        $user->refresh();
        $this->assertTrue($user->profile_settings['revenue_tracking_enabled']);
    }

    public function test_revenue_threshold_validation_rejects_negative_value(): void
    {
        $user = $this->createVatUserWithHousehold();

        $response = $this->actingAs($user)->patch(route('profile.quiz-settings.update'), [
            'has_vat' => true,
            'family_status' => 'single',
            'tracks_investments' => false,
            'revenue_threshold' => -1,
        ]);

        $response->assertSessionHasErrors(['revenue_threshold']);
    }

    // ───────────────────────────────────────────────────
    // RevenueNotificationService tests
    // ───────────────────────────────────────────────────

    public function test_notification_created_when_revenue_exceeds_80_percent(): void
    {
        $user = $this->createVatUserWithHousehold();
        $service = new RevenueNotificationService;

        $service->checkAndNotify($user, 68100, 85000); // ~80.1%

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
        ]);
        $user->refresh();
        $this->assertContains('80', $user->profile_settings['revenue_notified_levels']);
    }

    public function test_notification_not_duplicated_for_same_level(): void
    {
        $user = $this->createVatUserWithHousehold();
        $service = new RevenueNotificationService;

        $service->checkAndNotify($user, 68100, 85000);
        $service->checkAndNotify($user, 68100, 85000);

        $this->assertSame(1, AppNotification::where('user_id', $user->id)->count());
    }

    public function test_notification_created_when_revenue_exceeds_90_percent(): void
    {
        $user = $this->createVatUserWithHousehold();
        $service = new RevenueNotificationService;

        $service->checkAndNotify($user, 76600, 85000); // ~90.1%

        $notifications = AppNotification::where('user_id', $user->id)->get();
        $this->assertGreaterThanOrEqual(1, $notifications->count());
        $user->refresh();
        $this->assertContains('90', $user->profile_settings['revenue_notified_levels']);
    }

    public function test_critical_notification_created_when_revenue_exceeds_100_percent(): void
    {
        $user = $this->createVatUserWithHousehold();
        $service = new RevenueNotificationService;

        $service->checkAndNotify($user, 85001, 85000); // >100%

        $notifications = AppNotification::where('user_id', $user->id)->get();
        $this->assertGreaterThanOrEqual(1, $notifications->count());
        $user->refresh();
        $this->assertContains('100', $user->profile_settings['revenue_notified_levels']);
    }

    public function test_notified_levels_reset_when_revenue_drops_below_80_percent(): void
    {
        $user = $this->createVatUserWithHousehold([
            'revenue_notified_levels' => ['80'],
        ]);
        $service = new RevenueNotificationService;

        $service->checkAndNotify($user, 10000, 85000); // ~11.7% – below 80%

        $user->refresh();
        $this->assertEmpty($user->profile_settings['revenue_notified_levels']);
    }

    public function test_no_notification_when_threshold_is_zero(): void
    {
        $user = $this->createVatUserWithHousehold(['revenue_threshold' => 0]);
        $service = new RevenueNotificationService;

        $service->checkAndNotify($user, 90000, 0);

        $this->assertDatabaseMissing('notifications', ['user_id' => $user->id]);
    }
}
