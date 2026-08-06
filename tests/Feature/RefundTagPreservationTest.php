<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefundTagPreservationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private Category $expenseCategory;

    private Category $incomeCategory;

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
            'current_balance' => 500.00,
        ]);

        $this->expenseCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $this->incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'income',
        ]);
    }

    #[Test]
    public function refund_transaction_inherits_tags_from_original_expense(): void
    {
        $expense = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => -100.00,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
        ]);

        $tag = Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'RESO',
            'color' => '#6366f1',
        ]);
        $expense->tags()->sync([$tag->id]);

        $refund = app(RefundService::class)->createRefund([
            'original_transaction_id' => $expense->id,
            'amount' => 40.00,
            'user_id' => $this->user->id,
            'category_id' => $this->incomeCategory->id,
        ]);

        $refundTransaction = $refund->refundTransaction()->with('tags')->first();

        $this->assertNotNull($refundTransaction);
        $this->assertSame(['RESO'], $refundTransaction->tags->pluck('name')->all());
    }
}
