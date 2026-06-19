<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetNotificationService;
use App\Services\TransactionTrendNotificationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────

    private function createUserWithHousehold(): User
    {
        $user = User::factory()->create([
            'profile_settings' => [
                'has_vat' => false,
                'family_status' => 'single',
                'tracks_investments' => false,
            ],
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

    private function createAccount(User $user): Account
    {
        return Account::create([
            'household_id' => $user->active_household_id,
            'owner_user_id' => $user->id,
            'name' => 'Conto Test',
            'type' => 'bank',
            'currency_code' => 'EUR',
            'initial_balance' => 0,
            'active' => true,
        ]);
    }

    private function createExpenseCategory(User $user): Category
    {
        return Category::factory()->expense()->create([
            'household_id' => $user->active_household_id,
            'name' => 'Alimentari',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // BudgetNotificationService Tests
    // ─────────────────────────────────────────────────────────────

    public function test_budget_notification_created_when_exceeded(): void
    {
        $user = $this->createUserWithHousehold();
        $account = $this->createAccount($user);
        $category = $this->createExpenseCategory($user);

        $budget = Budget::create([
            'household_id' => $user->active_household_id,
            'category_id' => $category->id,
            'amount' => 100,
            'currency_code' => 'EUR',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);

        // Spendi 110 su un budget di 100
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -110,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
            'is_private' => false,
        ]);

        (new BudgetNotificationService)->checkAndNotify($user, $user->active_household_id);

        $notification = AppNotification::where('user_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('superato', strtolower($notification->title));
    }

    public function test_budget_notification_created_at_80_percent(): void
    {
        $user = $this->createUserWithHousehold();
        $account = $this->createAccount($user);
        $category = $this->createExpenseCategory($user);

        Budget::create([
            'household_id' => $user->active_household_id,
            'category_id' => $category->id,
            'amount' => 100,
            'currency_code' => 'EUR',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);

        // Spendi 82 su un budget di 100 (82%)
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -82,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
            'is_private' => false,
        ]);

        (new BudgetNotificationService)->checkAndNotify($user, $user->active_household_id);

        $this->assertSame(1, AppNotification::where('user_id', $user->id)->count());
        $notification = AppNotification::where('user_id', $user->id)->first();
        $this->assertStringContainsString('80', $notification->title);
    }

    public function test_budget_notification_not_duplicated(): void
    {
        $user = $this->createUserWithHousehold();
        $account = $this->createAccount($user);
        $category = $this->createExpenseCategory($user);

        Budget::create([
            'household_id' => $user->active_household_id,
            'category_id' => $category->id,
            'amount' => 100,
            'currency_code' => 'EUR',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -110,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
            'is_private' => false,
        ]);

        $service = new BudgetNotificationService;
        $service->checkAndNotify($user, $user->active_household_id);
        $service->checkAndNotify($user, $user->active_household_id);

        // Non deve creare duplicati
        $this->assertSame(1, AppNotification::where('user_id', $user->id)->count());
    }

    public function test_no_budget_notification_when_under_80_percent(): void
    {
        $user = $this->createUserWithHousehold();
        $account = $this->createAccount($user);
        $category = $this->createExpenseCategory($user);

        Budget::create([
            'household_id' => $user->active_household_id,
            'category_id' => $category->id,
            'amount' => 100,
            'currency_code' => 'EUR',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
            'is_private' => false,
        ]);

        (new BudgetNotificationService)->checkAndNotify($user, $user->active_household_id);

        $this->assertSame(0, AppNotification::where('user_id', $user->id)->count());
    }

    // ─────────────────────────────────────────────────────────────
    // TransactionTrendNotificationService Tests
    // ─────────────────────────────────────────────────────────────

    public function test_expense_increase_notification_created(): void
    {
        $user = $this->createUserWithHousehold();
        $service = new TransactionTrendNotificationService;

        $service->checkAndNotify(
            $user,
            ['income' => 2000, 'expenses' => 1500, 'net' => 500, 'transaction_count' => 10],
            ['income' => 2000, 'expenses' => 1000, 'net' => 1000, 'transaction_count' => 8],
            'marzo 2026',
            'febbraio 2026'
        );

        $notification = AppNotification::where('user_id', $user->id)
            ->where('notification_key', 'like', 'trend_expense_increase_%')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Uscite', $notification->title);
    }

    public function test_income_increase_notification_created(): void
    {
        $user = $this->createUserWithHousehold();
        $service = new TransactionTrendNotificationService;

        $service->checkAndNotify(
            $user,
            ['income' => 3000, 'expenses' => 1000, 'net' => 2000, 'transaction_count' => 10],
            ['income' => 2000, 'expenses' => 1000, 'net' => 1000, 'transaction_count' => 8],
            'marzo 2026',
            'febbraio 2026'
        );

        $notification = AppNotification::where('user_id', $user->id)
            ->where('notification_key', 'like', 'trend_income_increase_%')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('Entrate', $notification->title);
    }

    public function test_no_trend_notification_when_change_below_threshold(): void
    {
        $user = $this->createUserWithHousehold();
        $service = new TransactionTrendNotificationService;

        // Solo 5% di variazione, sotto la soglia del 20%
        $service->checkAndNotify(
            $user,
            ['income' => 2000, 'expenses' => 1050, 'net' => 950, 'transaction_count' => 10],
            ['income' => 2000, 'expenses' => 1000, 'net' => 1000, 'transaction_count' => 8],
            'marzo 2026',
            'febbraio 2026'
        );

        $this->assertSame(0, AppNotification::where('user_id', $user->id)->count());
    }

    public function test_no_trend_notification_when_previous_month_has_no_data(): void
    {
        $user = $this->createUserWithHousehold();
        $service = new TransactionTrendNotificationService;

        $service->checkAndNotify(
            $user,
            ['income' => 2000, 'expenses' => 1500, 'net' => 500, 'transaction_count' => 10],
            ['income' => 0, 'expenses' => 0, 'net' => 0, 'transaction_count' => 0],
            'marzo 2026',
            'febbraio 2026'
        );

        $this->assertSame(0, AppNotification::where('user_id', $user->id)->count());
    }

    public function test_trend_notification_not_duplicated(): void
    {
        $user = $this->createUserWithHousehold();
        $service = new TransactionTrendNotificationService;

        $currentStats = ['income' => 2000, 'expenses' => 1500, 'net' => 500, 'transaction_count' => 10];
        $lastMonthStats = ['income' => 2000, 'expenses' => 1000, 'net' => 1000, 'transaction_count' => 8];

        $service->checkAndNotify($user, $currentStats, $lastMonthStats, 'marzo 2026', 'febbraio 2026');
        $service->checkAndNotify($user, $currentStats, $lastMonthStats, 'marzo 2026', 'febbraio 2026');

        // Solo 1 notifica per il tipo expense_increase
        $count = AppNotification::where('user_id', $user->id)
            ->where('notification_key', 'like', 'trend_expense_increase_%')
            ->count();

        $this->assertSame(1, $count);
    }

    // ─────────────────────────────────────────────────────────────
    // NotificationController Tests
    // ─────────────────────────────────────────────────────────────

    public function test_mark_notification_as_read(): void
    {
        $user = $this->createUserWithHousehold();

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Test',
            'message' => 'Test message',
            'read' => false,
        ]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertTrue($notification->fresh()->read);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $user = $this->createUserWithHousehold();

        AppNotification::create(['user_id' => $user->id, 'title' => 'A', 'message' => 'Msg A', 'read' => false]);
        AppNotification::create(['user_id' => $user->id, 'title' => 'B', 'message' => 'Msg B', 'read' => false]);

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, AppNotification::where('user_id', $user->id)->where('read', false)->count());
    }

    public function test_cannot_mark_other_users_notification_as_read(): void
    {
        $user = $this->createUserWithHousehold();
        $otherUser = $this->createUserWithHousehold();

        $notification = AppNotification::create([
            'user_id' => $otherUser->id,
            'title' => 'Altrui',
            'message' => 'Non tua',
            'read' => false,
        ]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();
    }

    public function test_unread_count_available_via_notifications_header_endpoint(): void
    {
        $user = $this->createUserWithHousehold();

        AppNotification::create(['user_id' => $user->id, 'title' => 'A', 'message' => 'Msg', 'read' => false]);
        AppNotification::create(['user_id' => $user->id, 'title' => 'B', 'message' => 'Msg', 'read' => true]);

        $response = $this->actingAs($user)
            ->getJson(route('notifications.header'));

        $response->assertOk();
        $response->assertJsonPath('unread_count', 1);
    }

    public function test_recurring_detection_notification_has_action_url(): void
    {
        $user = $this->createUserWithHousehold();

        AppNotification::create([
            'user_id' => $user->id,
            'title' => '🔁 Nuove ricorrenze suggerite',
            'message' => 'Trovati nuovi suggerimenti.',
            'read' => false,
            'notification_key' => 'recurring_detect_1_2026-05-11',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('notifications.header'));

        $response->assertOk();
        $response->assertJsonPath('items.0.action_url', route('recurrence-detection.index'));
    }
}
