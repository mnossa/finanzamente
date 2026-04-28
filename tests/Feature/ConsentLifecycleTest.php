<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_creates_consent_and_event(): void
    {
        $user = User::factory()->create();
        $service = app(ConsentService::class);

        $consent = $service->setConsent($user, 'marketing_email', 'granted', [
            'source' => 'profile_settings',
            'policy_version' => 'privacy-v1.0',
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertSame('granted', $consent->status);
        $this->assertNotNull($consent->granted_at);
        $this->assertDatabaseHas('consent_events', [
            'consent_id' => $consent->id,
            'event_type' => 'granted',
            'new_status' => 'granted',
            'policy_version' => 'privacy-v1.0',
        ]);
    }

    public function test_revoke_updates_same_row_and_adds_event(): void
    {
        $user = User::factory()->create();
        $service = app(ConsentService::class);

        $granted = $service->setConsent($user, 'analytics_tracking', 'granted', [
            'source' => 'profile_settings',
            'policy_version' => 'privacy-v1.0',
        ]);

        $revoked = $service->setConsent($user, 'analytics_tracking', 'revoked', [
            'source' => 'profile_settings',
            'policy_version' => 'privacy-v1.1',
        ]);

        $this->assertSame($granted->id, $revoked->id);
        $this->assertSame('revoked', $revoked->status);
        $this->assertNotNull($revoked->revoked_at);
        $this->assertDatabaseCount('consents', 1);
        $this->assertDatabaseHas('consent_events', [
            'consent_id' => $revoked->id,
            'event_type' => 'revoked',
            'old_status' => 'granted',
            'new_status' => 'revoked',
            'policy_version' => 'privacy-v1.1',
        ]);
    }
}
