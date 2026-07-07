<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\MollieService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriptionPaymentMethodSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_subscription_page_includes_payment_method_summary_for_active_pro(): void
    {
        $user = User::factory()->create([
            'plan' => 'pro',
            'mollie_customer_id' => 'cst_test',
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'currency' => 'EUR',
            'amount_cents' => 299,
            'mollie_mandate_id' => 'mdt_test',
        ]);

        $mock = Mockery::mock(MollieService::class);
        $mock->shouldReceive('getPaymentMethodSummary')
            ->once()
            ->andReturn([
                'method' => 'creditcard',
                'label' => 'Visa',
                'last_digits' => '4242',
                'display' => 'Visa •••• 4242',
            ]);
        $this->app->instance(MollieService::class, $mock);

        $response = $this->actingAs($user)->withoutVite()->get('/profilo/abbonamento');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Subscription')
            ->where('paymentMethodSummary.display', 'Visa •••• 4242')
        );
    }
}
