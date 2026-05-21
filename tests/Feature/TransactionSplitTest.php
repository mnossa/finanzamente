<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionSplitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $accountA;

    private Account $accountB;

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

        $this->accountA = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Contanti',
            'type' => 'cash',
            'initial_balance' => 100,
            'current_balance' => 100,
        ]);

        $this->accountB = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Banca',
            'initial_balance' => 200,
            'current_balance' => 200,
        ]);

        $this->category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);
    }

    #[Test]
    public function can_store_transaction_with_empty_splits_array(): void
    {
        $this->actingAs($this->user)->post(route('transactions.store'), [
            'account_id' => $this->accountA->id,
            'category_id' => $this->category->id,
            'amount' => 25,
            'date' => now()->toDateString(),
            'description' => 'Senza split',
            'splits' => [],
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $transaction = Transaction::where('description', 'Senza split')->first();
        $this->assertNotNull($transaction);
        $this->assertNull($transaction->split_group_id);
        $this->assertSame($this->accountA->id, $transaction->account_id);
    }

    #[Test]
    public function can_store_transaction_without_splits_key(): void
    {
        $this->actingAs($this->user)->post(route('transactions.store'), [
            'account_id' => $this->accountA->id,
            'category_id' => $this->category->id,
            'amount' => 15,
            'date' => now()->toDateString(),
            'description' => 'Chiave splits assente',
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Transaction::where('description', 'Chiave splits assente')->first());
    }

    #[Test]
    public function rejects_split_payment_with_only_one_line(): void
    {
        $this->actingAs($this->user)->post(route('transactions.store'), [
            'account_id' => $this->accountA->id,
            'category_id' => $this->category->id,
            'amount' => 40,
            'date' => now()->toDateString(),
            'splits' => [
                ['account_id' => $this->accountA->id, 'amount' => 40],
            ],
        ])->assertSessionHasErrors('splits');
    }

    #[Test]
    public function can_create_split_payment_across_two_accounts(): void
    {
        $this->actingAs($this->user)->post(route('transactions.store'), [
            'account_id' => $this->accountA->id,
            'category_id' => $this->category->id,
            'amount' => 50,
            'date' => now()->toDateString(),
            'description' => 'Spesa mista',
            'splits' => [
                ['account_id' => $this->accountA->id, 'amount' => 20],
                ['account_id' => $this->accountB->id, 'amount' => 30],
            ],
        ])->assertRedirect();

        $groupIds = Transaction::where('description', 'Spesa mista')
            ->pluck('split_group_id')
            ->unique()
            ->filter();

        $this->assertCount(1, $groupIds);
        $this->assertCount(2, Transaction::where('split_group_id', $groupIds->first())->get());

        $this->accountA->refresh();
        $this->accountB->refresh();
        $this->assertSame(80.0, (float) $this->accountA->current_balance);
        $this->assertSame(170.0, (float) $this->accountB->current_balance);
    }
}
