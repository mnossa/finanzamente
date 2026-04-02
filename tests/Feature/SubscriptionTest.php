<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_plan_selection_page_is_accessible_as_guest(): void
    {
        $response = $this->get('/scegli-piano');
        $response->assertStatus(200);
    }

    public function test_register_page_accepts_plan_query_param(): void
    {
        $response = $this->get('/registrati?plan=base&billing_cycle=monthly');
        $response->assertStatus(200);
    }

    public function test_register_page_falls_back_to_base_for_invalid_plan(): void
    {
        $response = $this->get('/registrati?plan=nonexistent');
        $response->assertStatus(200);
    }

    public function test_new_user_is_created_with_base_plan_by_default(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'selected_plan' => 'base',
            'billing_cycle' => 'monthly',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'plan' => 'base',
        ]);
    }

    public function test_new_user_choosing_pro_stays_on_base_until_payment(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Pro User',
            'email' => 'pro@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'selected_plan' => 'pro',
            'billing_cycle' => 'monthly',
        ]);

        $this->assertAuthenticated();
        // User starts with base plan; pro gets activated only after successful payment
        $this->assertDatabaseHas('users', [
            'email' => 'pro@example.com',
            'plan' => 'base',
        ]);
    }

    public function test_pro_plan_selection_stores_pending_plan_in_session(): void
    {
        $response = $this->post('/registrati', [
            'name' => 'Pro User',
            'email' => 'pro2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_type' => 'persona',
            'selected_plan' => 'pro',
            'billing_cycle' => 'annual',
        ]);

        $response->assertSessionHas('pending_pro_plan', [
            'billing_cycle' => 'annual',
        ]);
    }

    public function test_subscription_page_is_accessible_when_authenticated(): void
    {
        $user = User::factory()->create(['plan' => 'base']);
        $this->actingAs($user);

        $response = $this->withoutVite()->get('/profilo/abbonamento');
        $response->assertStatus(200);
    }

    public function test_subscription_page_is_not_accessible_as_guest(): void
    {
        $response = $this->get('/profilo/abbonamento');
        $response->assertRedirect('/accedi');
    }

    public function test_plan_service_returns_correct_plans(): void
    {
        $service = app(PlanService::class);
        $plans = $service->getPlans();

        $this->assertArrayHasKey('base', $plans);
        $this->assertArrayHasKey('pro', $plans);
        $this->assertEquals(0, $plans['base']['price_monthly_cents']);
        $this->assertGreaterThan(0, $plans['pro']['price_monthly_cents']);
    }

    public function test_plan_service_calculates_annual_discount_correctly(): void
    {
        config(['plans.annual_discount_percent' => 20]);
        config(['plans.plans.pro.price_monthly_cents' => 990]);

        $service = app(PlanService::class);
        $annualMonthly = $service->getAnnualMonthlyCents('pro');
        $annualTotal = $service->getAnnualTotalCents('pro');

        $this->assertEquals(792, $annualMonthly); // 990 * (1 - 0.20) = 792
        $this->assertEquals(9504, $annualTotal);  // 792 * 12 = 9504
    }

    public function test_plan_service_is_pro_enabled_by_default(): void
    {
        config(['plans.pro_enabled' => true]);
        $service = app(PlanService::class);
        $this->assertTrue($service->isProEnabled());
    }

    public function test_plan_service_pro_disabled_by_config(): void
    {
        config(['plans.pro_enabled' => false]);
        $service = app(PlanService::class);
        $this->assertFalse($service->isProEnabled());
    }

    public function test_subscription_model_is_active(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'currency' => 'EUR',
            'amount_cents' => 990,
        ]);

        $this->assertTrue($subscription->isActive());
        $this->assertFalse($subscription->isCancelled());
        $this->assertFalse($subscription->isPending());
    }

    public function test_user_active_subscription_returns_most_recent(): void
    {
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'cancelled',
            'currency' => 'EUR',
            'amount_cents' => 990,
        ]);

        $activeSubscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'currency' => 'EUR',
            'amount_cents' => 990,
        ]);

        $foundSubscription = $user->fresh()->activeSubscription();
        $this->assertNotNull($foundSubscription);
        $this->assertEquals($activeSubscription->id, $foundSubscription->id);
    }

    public function test_user_active_subscription_returns_null_when_no_subscription(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->activeSubscription());
    }

    public function test_checkout_redirects_when_pro_is_disabled(): void
    {
        config(['plans.pro_enabled' => false]);

        $user = User::factory()->create(['plan' => 'base']);
        $this->actingAs($user);

        $response = $this->post('/abbonamento/checkout', [
            'billing_cycle' => 'monthly',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('error');
    }

    public function test_billing_update_requires_auth(): void
    {
        $response = $this->patch('/abbonamento/fatturazione', [
            'billing_name' => 'Test',
            'billing_email' => 'test@example.com',
        ]);

        $response->assertRedirect('/accedi');
    }

    public function test_billing_update_is_rejected_without_subscription(): void
    {
        $user = User::factory()->create(['plan' => 'base']);
        $this->actingAs($user);

        $response = $this->patch('/abbonamento/fatturazione', [
            'billing_name' => 'Test',
            'billing_email' => 'test@example.com',
        ]);

        $response->assertRedirect(route('profile.subscription'));
        $response->assertSessionHas('error');
    }

    public function test_billing_update_succeeds_with_active_subscription(): void
    {
        $user = User::factory()->create(['plan' => 'pro']);
        $this->actingAs($user);

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'currency' => 'EUR',
            'amount_cents' => 990,
            'billing_name' => 'Old Name',
            'billing_email' => 'old@example.com',
        ]);

        $response = $this->patch('/abbonamento/fatturazione', [
            'billing_name' => 'New Name',
            'billing_email' => 'new@example.com',
        ]);

        $response->assertRedirect(route('profile.subscription'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'billing_name' => 'New Name',
            'billing_email' => 'new@example.com',
        ]);
    }

    public function test_formatted_amount_is_in_italian_format(): void
    {
        $subscription = new Subscription(['amount_cents' => 990, 'currency' => 'EUR']);
        $this->assertEquals('9,90 €', $subscription->formatted_amount);
    }
}
