<?php

namespace Tests\Feature;

use App\Mail\InvestmentPacReminderMail;
use App\Models\AppNotification;
use App\Models\Household;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\User;
use App\Services\InvestmentPacReminderFormatter;
use App\Services\InvestmentPacService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentPacReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private InvestmentAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);
    }

    #[Test]
    public function formatter_builds_pac_reminder_message(): void
    {
        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 150,
            'fees' => 1.5,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-06-05',
            'status' => 'active',
        ]);

        $due = Carbon::parse('2026-06-05');
        $details = app(InvestmentPacReminderFormatter::class)->format($pac, $due);

        $this->assertStringContainsString('iShares Core MSCI World', $details['message']);
        $this->assertStringContainsString('150,00', $details['message']);
        $this->assertStringContainsString('05/06/2026', $details['message']);
    }

    #[Test]
    public function remind_command_sends_notification_and_dedupes_email(): void
    {
        Carbon::setTestNow('2026-06-04');

        Mail::fake();
        Cache::flush();

        $pac = InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 200,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-06-05',
            'status' => 'active',
        ]);

        $nextDue = app(InvestmentPacService::class)->calculateNextExecutionDate($pac->fresh());
        $this->assertSame('2026-06-05', $nextDue?->toDateString());

        Artisan::call('investment-pacs:remind');

        $notification = AppNotification::where('user_id', $this->user->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('PAC', $notification->title);
        $this->assertStringContainsString('iShares Core MSCI World', $notification->message);

        Mail::assertSent(InvestmentPacReminderMail::class, 1);

        Artisan::call('investment-pacs:remind');

        Mail::assertSent(InvestmentPacReminderMail::class, 1);

        Carbon::setTestNow();
    }

    #[Test]
    public function remind_command_respects_disabled_preferences(): void
    {
        Carbon::setTestNow('2026-06-04');

        $this->user->update([
            'preferences' => [
                'notifications' => [
                    'upcoming_due_dates' => [
                        'frequency' => 'never',
                        'channels' => ['in_app', 'email'],
                    ],
                ],
            ],
        ]);

        InvestmentPac::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'investment_asset_id' => $this->asset->id,
            'amount' => 100,
            'adjust_for_inflation' => false,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-06-05',
            'status' => 'active',
        ]);

        Artisan::call('investment-pacs:remind');

        $this->assertSame(0, AppNotification::where('user_id', $this->user->id)->count());

        Carbon::setTestNow();
    }
}
