<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\MollieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MollieWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    #[Test]
    public function webhook_returns_401_when_secret_header_is_invalid(): void
    {
        config(['services.mollie.webhook_secret' => 'expected-secret']);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('getPayment');
        });

        $response = $this->post(route('mollie.webhook'), ['id' => 'tr_invalid'], [
            'X-Mollie-Webhook-Secret' => 'wrong-secret',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function webhook_accepts_valid_secret_and_processes_once_for_duplicate_id(): void
    {
        config(['services.mollie.webhook_secret' => 'expected-secret']);

        $payment = (object) [
            'id' => 'tr_duplicate',
            'status' => 'open',
            'metadata' => null,
        ];

        $this->mock(MollieService::class, function ($mock) use ($payment) {
            $mock->shouldReceive('getPayment')
                ->once()
                ->with('tr_duplicate')
                ->andReturn($payment);
        });

        $headers = ['X-Mollie-Webhook-Secret' => 'expected-secret'];

        $first = $this->post(route('mollie.webhook'), ['id' => 'tr_duplicate'], $headers);
        $second = $this->post(route('mollie.webhook'), ['id' => 'tr_duplicate'], $headers);

        $first->assertOk();
        $second->assertOk();
    }

    #[Test]
    public function webhook_without_id_returns_ok_and_does_not_call_mollie_api(): void
    {
        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('getPayment');
        });

        $response = $this->post(route('mollie.webhook'), []);

        $response->assertOk();
    }

    #[Test]
    public function webhook_e2e_mock_can_activate_pending_subscription_without_mollie_call(): void
    {
        $this->app['env'] = 'e2e';

        $user = User::factory()->create(['plan' => 'base']);
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'pending',
            'currency' => 'EUR',
            'amount_cents' => 990,
        ]);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('getPayment');
        });

        $response = $this->post(route('mollie.webhook'), [
            'e2e_mock' => '1',
            'subscription_id' => $subscription->id,
            'status' => 'paid',
            'sequence_type' => 'first',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
            'mollie_mandate_id' => 'mdt_e2e_mock',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'plan' => 'pro',
        ]);

        $this->app['env'] = 'testing';
    }
}
