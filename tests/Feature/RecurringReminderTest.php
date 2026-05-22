<?php

namespace Tests\Feature;

use App\Mail\RecurringReminderMail;
use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Services\RecurringReminderFormatter;
use App\Services\RecurringTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringReminderTest extends TestCase
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
    public function formatter_builds_specific_expense_message(): void
    {
        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
            'name' => 'Alimentari',
        ]);

        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'amount' => -45.50,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => now()->toDateString(),
            'description' => 'Spesa supermercato',
        ]);

        $due = Carbon::tomorrow();
        $details = app(RecurringReminderFormatter::class)->format($recurring, $due);

        $this->assertSame('uscita', $details['direction_label']);
        $this->assertStringContainsString('Alimentari', $details['message']);
        $this->assertStringContainsString('Spesa supermercato', $details['message']);
        $this->assertStringContainsString('45,50', $details['message']);
    }

    #[Test]
    public function remind_command_sends_enriched_notification_and_dedupes_email(): void
    {
        Carbon::setTestNow('2026-06-04');

        Mail::fake();
        Cache::flush();

        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
            'name' => 'Stipendio',
        ]);

        $tomorrow = Carbon::parse('2026-06-05');

        $recurring = RecurringTransaction::create([
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

        $nextDue = app(RecurringTransactionService::class)->calculateNextDueDate($recurring->fresh());
        $this->assertSame($tomorrow->toDateString(), $nextDue?->toDateString());

        Artisan::call('recurring:remind');

        $notification = AppNotification::where('user_id', $this->user->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('entrata', $notification->message);
        $this->assertStringContainsString('Stipendio', $notification->message);
        $this->assertStringContainsString('Stipendio mensile', $notification->message);

        Mail::assertSent(RecurringReminderMail::class, 1);

        Artisan::call('recurring:remind');

        Mail::assertSent(RecurringReminderMail::class, 1);

        Carbon::setTestNow();
    }
}
