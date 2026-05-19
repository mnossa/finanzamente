<?php

namespace Tests\Feature;

use App\Mail\RecurringDetectionMail;
use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\RecurringTransactionSuggestion;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurrenceDetectionService;
use App\Services\RecurringTransactionService;
use DomainException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurrenceDetectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private Category $category;

    private RecurrenceDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['user_type' => 'persona']);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => 'EUR',
            'active' => true,
        ]);

        $this->category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $this->service = app(RecurrenceDetectionService::class);
    }

    #[Test]
    public function it_detects_monthly_recurring_pattern(): void
    {
        // 4 transazioni mensili dello stesso importo e categoria
        foreach (range(1, 4) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -50.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(4 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $created = $this->service->detectForHousehold($this->household->id);

        $this->assertSame(1, $created);

        $suggestion = RecurringTransactionSuggestion::first();
        $this->assertNotNull($suggestion);
        $this->assertSame('monthly', $suggestion->detected_frequency);
        $this->assertSame('pending', $suggestion->status);
        $this->assertEquals(-50.00, (float) $suggestion->amount);
        $this->assertCount(4, $suggestion->transaction_ids);
    }

    #[Test]
    public function it_does_not_detect_pattern_with_fewer_than_3_transactions(): void
    {
        foreach (range(1, 2) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -30.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(2 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $created = $this->service->detectForHousehold($this->household->id);

        $this->assertSame(0, $created);
        $this->assertDatabaseCount('recurring_transaction_suggestions', 0);
    }

    #[Test]
    public function it_skips_already_linked_recurring_transactions(): void
    {
        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -80.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => Carbon::now()->subMonths(4),
            'last_generated_date' => Carbon::now()->subMonth(),
        ]);

        foreach (range(1, 4) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -80.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(4 - $i)->startOfMonth(),
                'recurring' => true,
                'recurring_transaction_id' => $recurring->id,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $created = $this->service->detectForHousehold($this->household->id);

        $this->assertSame(0, $created);
    }

    #[Test]
    public function it_does_not_create_duplicate_suggestions(): void
    {
        foreach (range(1, 3) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -25.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(3 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $this->service->detectForHousehold($this->household->id);
        $this->service->detectForHousehold($this->household->id);

        $this->assertDatabaseCount('recurring_transaction_suggestions', 1);
    }

    #[Test]
    public function it_detects_yearly_recurring_pattern(): void
    {
        foreach (range(1, 3) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -200.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subYears(3 - $i)->startOfYear(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $created = $this->service->detectForHousehold($this->household->id);

        $this->assertSame(1, $created);
        $this->assertSame('yearly', RecurringTransactionSuggestion::first()->detected_frequency);
    }

    #[Test]
    public function recurring_detect_command_sends_email_to_household_members(): void
    {
        Mail::fake();

        foreach (range(1, 4) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -42.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(4 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        Artisan::call('recurring:detect', [
            '--household' => $this->household->id,
        ]);

        Mail::assertSent(RecurringDetectionMail::class, function (RecurringDetectionMail $mail) {
            return $mail->hasTo($this->user->email)
                && $mail->household->is($this->household)
                && $mail->suggestionsCount >= 1;
        });
    }

    #[Test]
    public function recurring_detect_command_creates_in_app_notification_for_household_members(): void
    {
        foreach (range(1, 4) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -42.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(4 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $exitCode = Artisan::call('recurring:detect', [
            '--household' => $this->household->id,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => '🔁 Nuove ricorrenze suggerite',
            'read' => false,
        ]);

        $this->assertSame(
            1,
            AppNotification::where('user_id', $this->user->id)
                ->where('notification_key', 'like', "recurring_detect_{$this->household->id}_%")
                ->count()
        );
    }

    #[Test]
    public function recurring_detect_command_notifies_even_when_only_pending_suggestions_exist(): void
    {
        RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50.00,
            'currency_code' => 'EUR',
            'description' => 'Abbonamento',
            'detected_frequency' => 'monthly',
            'confidence' => 0.9,
            'status' => 'pending',
            'transaction_ids' => [],
        ]);

        $exitCode = Artisan::call('recurring:detect', [
            '--household' => $this->household->id,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => '🔁 Nuove ricorrenze suggerite',
            'read' => false,
        ]);
    }

    #[Test]
    public function it_auto_closes_recurrence_for_stale_monthly_pattern_when_accepting_suggestion(): void
    {
        $transactions = collect(range(1, 3))->map(function ($i) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -59.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(6 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        $suggestion = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -59.00,
            'currency_code' => 'EUR',
            'description' => 'Abbonamento vecchio',
            'detected_frequency' => 'monthly',
            'confidence' => 0.9,
            'status' => 'pending',
            'transaction_ids' => $transactions->pluck('id')->all(),
        ]);

        $recurring = $this->service->acceptSuggestion($suggestion, app(RecurringTransactionService::class))->recurring;

        $this->assertNotNull($recurring->end_date);
        $this->assertTrue($recurring->end_date->isSameDay($transactions->last()->date));
        $this->assertSame(
            3,
            Transaction::whereIn('id', $transactions->pluck('id'))
                ->where('recurring', true)
                ->where('recurring_transaction_id', $recurring->id)
                ->count()
        );
    }

    #[Test]
    public function it_keeps_recurrence_open_for_recent_monthly_pattern_when_accepting_suggestion(): void
    {
        $transactions = collect(range(1, 3))->map(function ($i) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -39.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(3 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        $suggestion = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -39.00,
            'currency_code' => 'EUR',
            'description' => 'Abbonamento attivo',
            'detected_frequency' => 'monthly',
            'confidence' => 0.95,
            'status' => 'pending',
            'transaction_ids' => $transactions->pluck('id')->all(),
        ]);

        $recurring = $this->service->acceptSuggestion($suggestion, app(RecurringTransactionService::class))->recurring;

        $this->assertNull($recurring->end_date);
    }

    #[Test]
    public function it_keeps_recurrence_open_when_newer_similar_description_exists_even_if_pattern_is_stale(): void
    {
        $transactions = collect(range(1, 3))->map(function ($i) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -1200.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(10 - $i)->startOfMonth(),
                'description' => 'Stipendio Lara',
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        // Movimento più recente con stessa descrizione ma importo diverso:
        // deve evitare il falso positivo "dismessa".
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -1300.00,
            'currency_code' => 'EUR',
            'date' => Carbon::now()->subMonth()->startOfMonth(),
            'description' => 'Stipendio Lara',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        $suggestion = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -1200.00,
            'currency_code' => 'EUR',
            'description' => 'Stipendio Lara',
            'detected_frequency' => 'monthly',
            'confidence' => 0.95,
            'status' => 'pending',
            'transaction_ids' => $transactions->pluck('id')->all(),
        ]);

        $recurring = $this->service->acceptSuggestion($suggestion, app(RecurringTransactionService::class))->recurring;

        $this->assertNull($recurring->end_date);
    }

    #[Test]
    public function it_allows_forcing_active_or_closed_mode_when_accepting_suggestion(): void
    {
        $transactions = collect(range(1, 3))->map(function ($i) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -75.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(8 - $i)->startOfMonth(),
                'description' => 'Assegno unico',
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        $suggestionActive = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -75.00,
            'currency_code' => 'EUR',
            'description' => 'Assegno unico',
            'detected_frequency' => 'monthly',
            'confidence' => 0.9,
            'status' => 'pending',
            'transaction_ids' => $transactions->pluck('id')->all(),
        ]);

        $recurringActive = $this->service->acceptSuggestion(
            $suggestionActive,
            app(RecurringTransactionService::class),
            'active'
        )->recurring;
        $this->assertNull($recurringActive->end_date);

        $suggestionClosed = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -75.00,
            'currency_code' => 'EUR',
            'description' => 'Assegno unico',
            'detected_frequency' => 'monthly',
            'confidence' => 0.9,
            'status' => 'pending',
            'transaction_ids' => $transactions->pluck('id')->all(),
        ]);

        $recurringClosed = $this->service->acceptSuggestion(
            $suggestionClosed,
            app(RecurringTransactionService::class),
            'closed'
        )->recurring;
        $this->assertNotNull($recurringClosed->end_date);
    }

    #[Test]
    public function accepting_suggestion_removes_future_transactions_and_keeps_last_generated_on_historical_date(): void
    {
        $past = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -650.00,
            'currency_code' => 'EUR',
            'date' => Carbon::now()->subMonth()->startOfMonth(),
            'description' => 'Mutuo casa',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        $future = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -650.00,
            'currency_code' => 'EUR',
            'date' => Carbon::now()->addMonth()->startOfMonth(),
            'description' => 'Mutuo casa',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        $suggestion = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -650.00,
            'currency_code' => 'EUR',
            'description' => 'Mutuo casa',
            'detected_frequency' => 'monthly',
            'confidence' => 0.95,
            'status' => 'pending',
            'transaction_ids' => [$past->id, $future->id],
        ]);

        $result = $this->service->acceptSuggestion(
            $suggestion,
            app(RecurringTransactionService::class),
            'active'
        );

        $this->assertSame(1, $result->removedFutureTransactionCount);
        $recurring = $result->recurring;

        $past->refresh();
        $this->assertTrue($past->recurring);
        $this->assertSame($recurring->id, $past->recurring_transaction_id);

        $this->assertSoftDeleted('transactions', ['id' => $future->id]);
        $this->assertEquals(
            Carbon::parse($past->date)->toDateString(),
            Carbon::parse($recurring->last_generated_date)->toDateString()
        );
    }

    #[Test]
    public function accept_suggestion_redirect_flash_mentions_removed_future_transactions(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $past = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -650.00,
            'currency_code' => 'EUR',
            'date' => Carbon::now()->subMonth()->startOfMonth(),
            'description' => 'Mutuo casa',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        $future = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -650.00,
            'currency_code' => 'EUR',
            'date' => Carbon::now()->addMonth()->startOfMonth(),
            'description' => 'Mutuo casa',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        $suggestion = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -650.00,
            'currency_code' => 'EUR',
            'description' => 'Mutuo casa',
            'detected_frequency' => 'monthly',
            'confidence' => 0.95,
            'status' => 'pending',
            'transaction_ids' => [$past->id, $future->id],
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('recurrence-detection.accept', $suggestion), ['mode' => 'active']);

        $response->assertSessionHas(
            'success',
            fn (mixed $flash): bool => is_string($flash) && str_contains(
                $flash,
                'Rimosse 1 transazioni future'
            )
        );
    }

    #[Test]
    public function recurring_strip_future_command_soft_deletes_future_linked_transactions(): void
    {
        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -650.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => Carbon::now()->subMonths(2)->startOfMonth(),
            'end_date' => null,
            'description' => 'Mutuo casa',
            'last_generated_date' => Carbon::now()->addMonth()->startOfMonth(),
            'debt_credit_id' => null,
        ]);

        $past = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -650.00,
            'currency_code' => 'EUR',
            'date' => Carbon::now()->subMonth()->startOfMonth(),
            'description' => 'Mutuo casa',
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        $future = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -650.00,
            'currency_code' => 'EUR',
            'date' => Carbon::now()->addMonth()->startOfMonth(),
            'description' => 'Mutuo casa',
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        Artisan::call('recurring:strip-future', [
            '--id' => [$recurring->id],
        ]);

        $this->assertSoftDeleted('transactions', ['id' => $future->id]);
        $past->refresh();
        $this->assertNull($past->deleted_at);

        $recurring->refresh();
        $this->assertEquals(
            Carbon::parse($past->date)->toDateString(),
            $recurring->last_generated_date->toDateString()
        );
    }

    #[Test]
    public function index_marks_stale_suggestion_as_auto_closed_preview(): void
    {
        $transactions = collect(range(1, 3))->map(function ($i) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -25.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(7 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -25.00,
            'currency_code' => 'EUR',
            'description' => 'Streaming',
            'detected_frequency' => 'monthly',
            'confidence' => 0.8,
            'status' => 'pending',
            'transaction_ids' => $transactions->pluck('id')->all(),
        ]);

        $response = $this->actingAs($this->user)->get(route('recurrence-detection.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('RecurringTransactions/Suggestions')
            ->where('suggestions.0.will_auto_close', true)
            ->where('suggestions.0.auto_close_end_date', Carbon::parse($transactions->last()->date)->format('Y-m-d'))
        );
    }

    #[Test]
    public function index_exposes_gap_insights_for_suggestions_with_holes(): void
    {
        $dates = [
            Carbon::now()->subMonths(2)->startOfMonth(),
            Carbon::now()->subDays(12), // buco interno marcato (> soglia mensile)
            Carbon::now()->startOfMonth(),
        ];

        $transactions = collect($dates)->map(function (Carbon $date) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -60.00,
                'currency_code' => 'EUR',
                'date' => $date,
                'description' => 'Assegno unico',
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -60.00,
            'currency_code' => 'EUR',
            'description' => 'Assegno unico',
            'detected_frequency' => 'monthly',
            'confidence' => 0.85,
            'status' => 'pending',
            'transaction_ids' => $transactions->pluck('id')->all(),
        ]);

        $response = $this->actingAs($this->user)->get(route('recurrence-detection.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('RecurringTransactions/Suggestions')
            ->where('suggestions.0.has_gaps', true)
            ->where('suggestions.0.missing_occurrences', 1)
            ->where('suggestions.0.has_internal_gaps', true)
            ->where('suggestions.0.has_trailing_gap', false)
        );
    }

    #[Test]
    public function index_exposes_trailing_gap_when_last_occurrence_is_old(): void
    {
        $dates = [
            Carbon::now()->subMonths(6)->startOfMonth(),
            Carbon::now()->subMonths(5)->startOfMonth(),
            Carbon::now()->subMonths(4)->startOfMonth(),
        ];

        $transactions = collect($dates)->map(function (Carbon $date) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -45.00,
                'currency_code' => 'EUR',
                'date' => $date,
                'description' => 'Trailling gap test',
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -45.00,
            'currency_code' => 'EUR',
            'description' => 'Trailling gap test',
            'detected_frequency' => 'monthly',
            'confidence' => 0.75,
            'status' => 'pending',
            'transaction_ids' => $transactions->pluck('id')->all(),
        ]);

        $response = $this->actingAs($this->user)->get(route('recurrence-detection.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('RecurringTransactions/Suggestions')
            ->where('suggestions.0.has_trailing_gap', true)
            ->where('suggestions.0.trailing_missing_occurrences', 3)
        );
    }

    #[Test]
    public function index_exposes_amount_change_guidance_for_linked_suggestions(): void
    {
        $oldTransactions = collect(range(1, 3))->map(function ($i) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -100.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(8 - $i)->startOfMonth(),
                'description' => 'Allianz Assicurazione',
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        $newTransactions = collect(range(1, 3))->map(function ($i) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -120.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(4 - $i)->startOfMonth(),
                'description' => 'Allianz Assicurazione',
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'currency_code' => 'EUR',
            'description' => 'Allianz Assicurazione',
            'detected_frequency' => 'monthly',
            'confidence' => 0.8,
            'status' => 'pending',
            'transaction_ids' => $oldTransactions->pluck('id')->all(),
        ]);

        RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -120.00,
            'currency_code' => 'EUR',
            'description' => 'Allianz Assicurazione',
            'detected_frequency' => 'monthly',
            'confidence' => 0.85,
            'status' => 'pending',
            'transaction_ids' => $newTransactions->pluck('id')->all(),
        ]);

        $response = $this->actingAs($this->user)->get(route('recurrence-detection.index'));
        $suggestions = collect($response->viewData('page')['props']['suggestions']);

        $oldSuggestion = $suggestions->firstWhere('amount', -100.0);
        $newSuggestion = $suggestions->firstWhere('amount', -120.0);

        $this->assertNotNull($oldSuggestion['amount_change_guidance']);
        $this->assertNotNull($newSuggestion['amount_change_guidance']);
        $this->assertSame('closed', $oldSuggestion['amount_change_guidance']['recommended_mode']);
        $this->assertSame('active', $newSuggestion['amount_change_guidance']['recommended_mode']);
        $this->assertSame(-120.0, (float) $oldSuggestion['amount_change_guidance']['pair_amount']);
        $this->assertSame(-100.0, (float) $newSuggestion['amount_change_guidance']['pair_amount']);
    }

    #[Test]
    public function recurring_detect_refresh_pending_rebuilds_pending_suggestions(): void
    {
        RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -88.00,
            'currency_code' => 'EUR',
            'description' => 'Vecchio pending',
            'detected_frequency' => 'monthly',
            'confidence' => 0.7,
            'status' => 'pending',
            'transaction_ids' => [],
        ]);

        foreach (range(1, 3) as $i) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -88.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(3 - $i)->startOfMonth(),
                'description' => 'Vecchio pending',
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $exitCode = Artisan::call('recurring:detect', [
            '--household' => $this->household->id,
            '--refresh-pending' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            1,
            RecurringTransactionSuggestion::where('status', 'pending')
                ->where('account_id', $this->account->id)
                ->count()
        );
        $this->assertGreaterThan(
            0,
            count(RecurringTransactionSuggestion::where('status', 'pending')->first()->transaction_ids ?? [])
        );
    }

    #[Test]
    public function closed_fill_gaps_mode_backfills_missing_internal_occurrences(): void
    {
        $first = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -30.00,
            'currency_code' => 'EUR',
            'date' => Carbon::create(2026, 1, 1),
            'description' => 'Test buco',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);
        $second = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -30.00,
            'currency_code' => 'EUR',
            'date' => Carbon::create(2026, 3, 1),
            'description' => 'Test buco',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);
        $third = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -30.00,
            'currency_code' => 'EUR',
            'date' => Carbon::create(2026, 4, 1),
            'description' => 'Test buco',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        $suggestion = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -30.00,
            'currency_code' => 'EUR',
            'description' => 'Test buco',
            'detected_frequency' => 'monthly',
            'confidence' => 0.9,
            'status' => 'pending',
            'transaction_ids' => [$first->id, $second->id, $third->id],
        ]);

        $recurring = $this->service->acceptSuggestion(
            $suggestion,
            app(RecurringTransactionService::class),
            'closed_fill_gaps'
        )->recurring;

        $this->assertNotNull($recurring->end_date);
        // 1 feb 2026 è domenica → occorrenza posticipata al primo giorno lavorativo (2 feb)
        $this->assertDatabaseHas('transactions', [
            'recurring_transaction_id' => $recurring->id,
            'date' => '2026-02-02 00:00:00',
            'amount' => -30.00,
        ]);
    }

    #[Test]
    public function active_fill_gaps_mode_backfills_missing_internal_occurrences_and_keeps_open(): void
    {
        $first = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -30.00,
            'currency_code' => 'EUR',
            'date' => Carbon::create(2026, 1, 1),
            'description' => 'Test buco attivo',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);
        $second = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -30.00,
            'currency_code' => 'EUR',
            'date' => Carbon::create(2026, 3, 1),
            'description' => 'Test buco attivo',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);
        $third = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -30.00,
            'currency_code' => 'EUR',
            'date' => Carbon::create(2026, 4, 1),
            'description' => 'Test buco attivo',
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        $suggestion = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -30.00,
            'currency_code' => 'EUR',
            'description' => 'Test buco attivo',
            'detected_frequency' => 'monthly',
            'confidence' => 0.9,
            'status' => 'pending',
            'transaction_ids' => [$first->id, $second->id, $third->id],
        ]);

        $recurring = $this->service->acceptSuggestion(
            $suggestion,
            app(RecurringTransactionService::class),
            'active_fill_gaps'
        )->recurring;

        $this->assertNull($recurring->end_date);
        // 1 feb 2026 è domenica → occorrenza posticipata al primo giorno lavorativo (2 feb)
        $this->assertDatabaseHas('transactions', [
            'recurring_transaction_id' => $recurring->id,
            'date' => '2026-02-02 00:00:00',
            'amount' => -30.00,
        ]);
    }

    #[Test]
    public function it_detects_recent_variable_amount_monthly_pattern(): void
    {
        $amounts = [402.00, 407.60, 413.20, 407.60];

        foreach ($amounts as $index => $amount) {
            Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => $amount,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(3 - $index)->startOfMonth(),
                'description' => 'Assegno unico',
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        }

        $created = $this->service->detectForHousehold($this->household->id);

        $this->assertSame(1, $created);
        $suggestion = RecurringTransactionSuggestion::first();
        $this->assertNotNull($suggestion);
        $this->assertSame('monthly', $suggestion->detected_frequency);
        $this->assertCount(4, $suggestion->transaction_ids);
    }

    #[Test]
    public function index_includes_soft_deleted_transactions_in_preview(): void
    {
        $transactions = collect(range(1, 3))->map(function ($i) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -16.13,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(3 - $i)->startOfMonth(),
                'description' => 'Allianz test',
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        $transactions->first()->delete();

        RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -16.13,
            'currency_code' => 'EUR',
            'description' => 'Allianz test',
            'detected_frequency' => 'monthly',
            'confidence' => 0.9,
            'status' => 'pending',
            'transaction_ids' => $transactions->pluck('id')->all(),
        ]);

        $response = $this->actingAs($this->user)->get(route('recurrence-detection.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('RecurringTransactions/Suggestions')
            // Inertia passa array JSON come Collection al closure `where`.
            ->where('suggestions.0.transactions', fn ($rows): bool => count($rows) === 3)
        );
    }

    #[Test]
    public function accept_suggestion_links_soft_deleted_historical_transactions(): void
    {
        $transactions = collect(range(1, 3))->map(function ($i) {
            return Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => -22.00,
                'currency_code' => 'EUR',
                'date' => Carbon::now()->subMonths(4 - $i)->startOfMonth(),
                'recurring' => false,
                'recurring_transaction_id' => null,
                'transfer_id' => null,
                'refund_id' => null,
            ]);
        });

        $transactions->first()->delete();

        $suggestion = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -22.00,
            'currency_code' => 'EUR',
            'description' => 'Soft delete link',
            'detected_frequency' => 'monthly',
            'confidence' => 0.9,
            'status' => 'pending',
            'transaction_ids' => $transactions->pluck('id')->all(),
        ]);

        $recurring = $this->service->acceptSuggestion($suggestion, app(RecurringTransactionService::class))->recurring;

        $this->assertSame(
            3,
            Transaction::withTrashed()->whereIn('id', $transactions->pluck('id'))
                ->where('recurring', true)
                ->where('recurring_transaction_id', $recurring->id)
                ->count()
        );
    }

    #[Test]
    public function accept_suggestion_throws_when_transaction_ids_do_not_resolve_for_account(): void
    {
        $otherHousehold = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $otherHousehold->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $otherAccount = Account::factory()->create([
            'household_id' => $otherHousehold->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => 'EUR',
            'active' => true,
        ]);

        $foreign = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $otherAccount->id,
            'category_id' => $this->category->id,
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'date' => Carbon::now()->subMonth()->startOfMonth(),
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        $suggestion = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'description' => 'Mismatch account',
            'detected_frequency' => 'monthly',
            'confidence' => 0.5,
            'status' => 'pending',
            'transaction_ids' => [$foreign->id],
        ]);

        $this->expectException(DomainException::class);

        $this->service->acceptSuggestion($suggestion, app(RecurringTransactionService::class));
    }

    #[Test]
    public function accept_route_redirects_with_error_when_no_transactions_for_suggestion(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $foreignHousehold = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $foreignHousehold->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $foreignAccount = Account::factory()->create([
            'household_id' => $foreignHousehold->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => 'EUR',
            'active' => true,
        ]);

        $foreign = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $foreignAccount->id,
            'category_id' => $this->category->id,
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'date' => Carbon::now()->subMonth()->startOfMonth(),
            'recurring' => false,
            'recurring_transaction_id' => null,
            'transfer_id' => null,
            'refund_id' => null,
        ]);

        $suggestion = RecurringTransactionSuggestion::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -10.00,
            'currency_code' => 'EUR',
            'description' => 'Mismatch account web',
            'detected_frequency' => 'monthly',
            'confidence' => 0.5,
            'status' => 'pending',
            'transaction_ids' => [$foreign->id],
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('recurrence-detection.accept', $suggestion), ['mode' => 'active']);

        $response->assertRedirect(route('recurrence-detection.index'));
        $response->assertSessionHas(
            'error',
            'Questo suggerimento non ha più transazioni collegate (forse eliminate dal registro). Ignoralo o rilancia il rilevamento.'
        );
    }
}
