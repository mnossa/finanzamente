<?php

namespace Tests\Feature;

use App\Mail\UpcomingDueWeeklyMail;
use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpcomingDueWeeklyNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

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

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function weekly_command_sends_digest_when_movements_exist(): void
    {
        Carbon::setTestNow('2026-06-09');

        Mail::fake();
        Cache::flush();

        $this->user->update([
            'preferences' => [
                'notifications' => [
                    'upcoming_due_dates' => [
                        'frequency' => 'weekly',
                        'channels' => ['in_app', 'email'],
                    ],
                ],
            ],
        ]);

        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
            'name' => 'Abbonamenti',
        ]);

        RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'amount' => -15,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-06-10',
            'description' => 'Streaming mensile',
        ]);

        Artisan::call('upcoming-due:notify-weekly');

        $notification = AppNotification::where('user_id', $this->user->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Prossime scadenze', $notification->title);
        $this->assertStringContainsString('Streaming mensile', $notification->message);

        Mail::assertSent(UpcomingDueWeeklyMail::class, 1);

        Carbon::setTestNow();
    }

    #[Test]
    public function weekly_command_skips_daily_preference(): void
    {
        Carbon::setTestNow('2026-06-09');

        $this->user->update([
            'preferences' => [
                'notifications' => [
                    'upcoming_due_dates' => [
                        'frequency' => 'daily',
                        'channels' => ['in_app'],
                    ],
                ],
            ],
        ]);

        Artisan::call('upcoming-due:notify-weekly');

        $this->assertSame(0, AppNotification::where('user_id', $this->user->id)->count());

        Carbon::setTestNow();
    }

    #[Test]
    public function daily_reminders_skip_weekly_preference(): void
    {
        Carbon::setTestNow('2026-06-04');
        Mail::fake();
        Cache::flush();

        $this->user->update([
            'preferences' => [
                'notifications' => [
                    'upcoming_due_dates' => [
                        'frequency' => 'weekly',
                        'channels' => ['in_app', 'email'],
                    ],
                ],
            ],
        ]);

        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
            'name' => 'Stipendio',
        ]);

        RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'amount' => 1500,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-05-05',
            'description' => 'Stipendio mensile',
            'last_generated_date' => '2026-05-05',
        ]);

        Artisan::call('recurring:remind');

        $this->assertSame(0, AppNotification::where('user_id', $this->user->id)->count());
        Mail::assertNothingSent();

        Carbon::setTestNow();
    }
}
