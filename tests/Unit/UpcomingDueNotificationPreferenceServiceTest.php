<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\UpcomingDueNotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpcomingDueNotificationPreferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private UpcomingDueNotificationPreferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UpcomingDueNotificationPreferenceService::class);
    }

    #[Test]
    public function defaults_to_daily_when_no_preferences(): void
    {
        $user = User::factory()->create();

        $this->assertSame('daily', $this->service->frequency($user));
        $this->assertTrue($this->service->isDaily($user));
    }

    #[Test]
    public function reads_explicit_weekly_frequency(): void
    {
        $user = User::factory()->create([
            'preferences' => [
                'notifications' => [
                    'upcoming_due_dates' => [
                        'frequency' => 'weekly',
                        'channels' => ['in_app'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($this->service->isWeekly($user));
        $this->assertSame(['in_app'], $this->service->channels($user));
    }

    #[Test]
    public function legacy_disabled_reminders_map_to_never(): void
    {
        $user = User::factory()->create([
            'preferences' => [
                'notifications' => [
                    'recurring_reminder' => ['enabled' => false, 'channels' => ['email']],
                    'investment_pac_reminder' => ['enabled' => false, 'channels' => ['email']],
                ],
            ],
        ]);

        $this->assertTrue($this->service->isNever($user));
    }
}
