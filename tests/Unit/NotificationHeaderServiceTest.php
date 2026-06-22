<?php

namespace Tests\Unit;

use App\Models\AppNotification;
use App\Models\Household;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\User;
use App\Services\NotificationHeaderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationHeaderServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pac_reminder_notification_resolves_to_pac_show_route(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);

        $pac = InvestmentPac::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'investment_asset_id' => $asset->id,
            'amount' => 100,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-06-05',
            'status' => 'active',
        ]);

        AppNotification::create([
            'user_id' => $user->id,
            'title' => 'PAC in scadenza',
            'message' => 'Versamento PAC previsto',
            'notification_key' => "investment_pac_remind_{$pac->id}_2026-06-05",
            'read' => false,
        ]);

        $payload = app(NotificationHeaderService::class)->forUser($user);

        $this->assertSame(route('investment-pacs.show', $pac->id), $payload['items'][0]['action_url']);
    }
}
