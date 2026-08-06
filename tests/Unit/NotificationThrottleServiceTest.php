<?php

namespace Tests\Unit;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\NotificationThrottleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationThrottleServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function blocks_new_suggestions_when_unread_cap_reached(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $index) {
            AppNotification::create([
                'user_id' => $user->id,
                'title' => "Suggerimento {$index}",
                'message' => 'Test',
                'notification_key' => "trend_expense_increase_2026-0{$index}",
                'read' => false,
            ]);
        }

        $service = app(NotificationThrottleService::class);

        $this->assertFalse($service->canCreateSuggestion($user, 'trend_income_decrease_2026-06'));
        $this->assertTrue($service->canCreateSuggestion($user, 'budget_1_80_2026-06'));
    }
}
