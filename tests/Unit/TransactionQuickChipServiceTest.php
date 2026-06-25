<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionQuickChipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionQuickChipServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionQuickChipService $service;

    private User $user;

    private Household $household;

    private Account $primaryAccount;

    private Account $secondaryAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TransactionQuickChipService::class);

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->primaryAccount = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Conto A',
        ]);

        $this->secondaryAccount = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Conto B',
        ]);
    }

    #[Test]
    public function returns_empty_when_user_has_no_household(): void
    {
        $user = User::factory()->create(['active_household_id' => null]);

        $this->assertSame([], $this->service->forUser($user));
    }

    #[Test]
    public function returns_empty_when_no_recent_transactions(): void
    {
        $this->assertSame([], $this->service->forUser($this->user));
    }

    #[Test]
    public function ranks_categories_by_frequency_with_recency_tiebreak(): void
    {
        $frequent = Category::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Alimentari',
            'type' => 'expense',
            'icon' => '🛒',
        ]);

        $rare = Category::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Benzina',
            'type' => 'expense',
        ]);

        foreach (range(1, 3) as $i) {
            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'account_id' => $this->primaryAccount->id,
                'category_id' => $frequent->id,
                'amount' => -10,
                'date' => now()->subDays($i)->toDateString(),
            ]);
        }

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->primaryAccount->id,
            'category_id' => $rare->id,
            'amount' => -20,
            'date' => now()->toDateString(),
        ]);

        $chips = $this->service->forUser($this->user);

        $this->assertCount(2, $chips);
        $this->assertSame($frequent->id, $chips[0]['category_id']);
        $this->assertSame('Alimentari', $chips[0]['label']);
        $this->assertSame('🛒', $chips[0]['icon']);
        $this->assertSame('expense', $chips[0]['type']);
    }

    #[Test]
    public function resolves_most_used_account_per_category(): void
    {
        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        Transaction::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'account_id' => $this->secondaryAccount->id,
            'category_id' => $category->id,
            'amount' => -5,
            'date' => now()->subDay()->toDateString(),
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->primaryAccount->id,
            'category_id' => $category->id,
            'amount' => -5,
            'date' => now()->toDateString(),
        ]);

        $chips = $this->service->forUser($this->user);

        $this->assertCount(1, $chips);
        $this->assertSame($this->secondaryAccount->id, $chips[0]['account_id']);
    }

    #[Test]
    public function excludes_private_transactions_from_other_users(): void
    {
        $otherUser = User::factory()->create();
        $this->household->users()->attach($otherUser->id, [
            'role' => 'member',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $otherUser->update(['active_household_id' => $this->household->id]);

        $privateCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Segreta',
            'type' => 'expense',
        ]);

        $visibleCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Visibile',
            'type' => 'expense',
        ]);

        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'account_id' => $this->primaryAccount->id,
            'category_id' => $privateCategory->id,
            'amount' => -50,
            'date' => now()->toDateString(),
            'is_private' => true,
        ]);

        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->primaryAccount->id,
            'category_id' => $visibleCategory->id,
            'amount' => -10,
            'date' => now()->toDateString(),
        ]);

        $chips = $this->service->forUser($this->user);

        $this->assertCount(1, $chips);
        $this->assertSame($visibleCategory->id, $chips[0]['category_id']);
    }

    #[Test]
    public function limits_chips_to_eight(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $category = Category::factory()->create([
                'household_id' => $this->household->id,
                'type' => 'expense',
                'name' => "Cat {$i}",
            ]);

            Transaction::factory()->create([
                'user_id' => $this->user->id,
                'account_id' => $this->primaryAccount->id,
                'category_id' => $category->id,
                'amount' => -$i,
                'date' => now()->subDays($i)->toDateString(),
            ]);
        }

        $this->assertCount(8, $this->service->forUser($this->user));
    }
}
