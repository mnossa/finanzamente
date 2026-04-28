<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_stores_hashed_ip_and_user_agent_only(): void
    {
        $user = User::factory()->create();
        $service = app(ConsentService::class);

        $service->setConsent($user, 'marketing_email', 'granted', [
            'source' => 'web_register',
            'policy_version' => 'privacy-v2.0',
            'ip' => '10.0.0.12',
            'user_agent' => 'Mozilla/5.0 test-agent',
        ]);

        $event = $user->consentEvents()->latest('id')->first();

        $this->assertNotNull($event);
        $this->assertNotNull($event->ip_hash);
        $this->assertNotNull($event->user_agent_hash);
        $this->assertNotEquals('10.0.0.12', $event->ip_hash);
        $this->assertNotEquals('Mozilla/5.0 test-agent', $event->user_agent_hash);
        $this->assertSame(64, strlen($event->ip_hash));
        $this->assertSame(64, strlen($event->user_agent_hash));
    }

    public function test_each_status_change_appends_new_event(): void
    {
        $user = User::factory()->create();
        $service = app(ConsentService::class);

        $service->setConsent($user, 'marketing_email', 'pending', ['source' => 'web_register', 'policy_version' => 'v1']);
        $service->setConsent($user, 'marketing_email', 'granted', ['source' => 'profile_settings', 'policy_version' => 'v2']);
        $service->setConsent($user, 'marketing_email', 'revoked', ['source' => 'profile_settings', 'policy_version' => 'v3']);

        $this->assertDatabaseCount('consent_events', 3);
        $this->assertDatabaseHas('consent_events', ['new_status' => 'pending']);
        $this->assertDatabaseHas('consent_events', ['new_status' => 'granted']);
        $this->assertDatabaseHas('consent_events', ['new_status' => 'revoked']);
    }
}
