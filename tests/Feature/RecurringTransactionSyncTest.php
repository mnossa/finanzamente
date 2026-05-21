<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringTransactionSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

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

        $this->category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);
    }

    #[Test]
    public function updating_recurring_template_syncs_linked_transactions(): void
    {
        $recurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -25.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => now()->subMonths(2)->toDateString(),
            'description' => 'Abbonamento base',
        ]);

        $linked = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -25.00,
            'currency_code' => 'EUR',
            'date' => now()->subMonth()->toDateString(),
            'description' => 'Abbonamento base',
            'recurring' => true,
            'recurring_transaction_id' => $recurring->id,
        ]);

        $newCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $recurring->update([
            'category_id' => $newCategory->id,
            'amount' => -30.00,
            'description' => 'Abbonamento premium',
        ]);

        $synced = app(RecurringTransactionService::class)->syncLinkedTransactionsFromTemplate($recurring);

        $this->assertSame(1, $synced);
        $linked->refresh();
        $this->assertSame($newCategory->id, $linked->category_id);
        $this->assertSame(-30.00, (float) $linked->amount);
        $this->assertStringStartsWith('Abbonamento premium', $linked->description);
    }
}
