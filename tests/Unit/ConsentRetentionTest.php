<?php

namespace Tests\Unit;

use App\Models\Consent;
use App\Models\ConsentEvent;
use App\Models\RetentionPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_command_anonymizes_and_prunes_events(): void
    {
        $user = User::factory()->create();
        $consent = Consent::query()->create([
            'user_id' => $user->id,
            'purpose' => 'analytics_tracking',
            'status' => 'granted',
            'source' => 'profile_settings',
            'legal_basis' => 'consent',
            'policy_version' => 'v1',
        ]);

        RetentionPolicy::query()->create([
            'policy_key' => 'consent_events_default',
            'description' => 'Default retention',
            'retention_days' => 30,
            'anonymize_after_days' => 7,
            'is_active' => true,
            'version' => '2026-04-28-v1',
        ]);

        ConsentEvent::query()->create([
            'consent_id' => $consent->id,
            'user_id' => $user->id,
            'event_type' => 'granted',
            'old_status' => null,
            'new_status' => 'granted',
            'source' => 'profile_settings',
            'ip_hash' => str_repeat('a', 64),
            'user_agent_hash' => str_repeat('b', 64),
            'policy_version' => 'v1',
            'occurred_at' => now()->subDays(10),
            'metadata' => null,
            'created_at' => now()->subDays(10),
        ]);

        ConsentEvent::query()->create([
            'consent_id' => $consent->id,
            'user_id' => $user->id,
            'event_type' => 'revoked',
            'old_status' => 'granted',
            'new_status' => 'revoked',
            'source' => 'profile_settings',
            'ip_hash' => str_repeat('c', 64),
            'user_agent_hash' => str_repeat('d', 64),
            'policy_version' => 'v1',
            'occurred_at' => now()->subDays(40),
            'metadata' => null,
            'created_at' => now()->subDays(40),
        ]);

        $this->artisan('consents:enforce-retention')
            ->expectsOutput('Anonymized events: 2')
            ->expectsOutput('Deleted events: 1')
            ->assertExitCode(0);

        $this->assertDatabaseCount('consent_events', 1);
        $this->assertDatabaseHas('consent_events', [
            'event_type' => 'granted',
            'ip_hash' => null,
            'user_agent_hash' => null,
        ]);
    }
}
