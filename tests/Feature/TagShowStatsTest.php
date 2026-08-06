<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TagShowStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private Household $household;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        Carbon::setTestNow('2026-07-15');

        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();

        $this->household = Household::factory()->create(['owner_user_id' => $this->userA->id]);
        $this->household->users()->attach($this->userA->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->household->users()->attach($this->userB->id, [
            'role' => 'member',
            'permissions' => json_encode(['manage' => true]),
        ]);

        $this->userA->update(['active_household_id' => $this->household->id]);
        $this->userB->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->userA->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function user_cannot_view_other_users_tag_show(): void
    {
        $foreignTag = Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->userA->id,
            'name' => 'SEGRETO',
            'color' => '#6366f1',
        ]);

        $this->actingAs($this->userB)
            ->get(route('tags.show', $foreignTag))
            ->assertForbidden();
    }

    #[Test]
    public function show_aggregates_only_tagged_transactions_in_selected_month(): void
    {
        $tag = Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->userA->id,
            'name' => 'VIAGGIO',
            'color' => '#22c55e',
        ]);
        $otherTag = Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->userA->id,
            'name' => 'ALTRO',
            'color' => '#ef4444',
        ]);

        $food = Category::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Cibo',
            'type' => 'expense',
        ]);
        $salary = Category::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Stipendio',
            'type' => 'income',
        ]);

        $income = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->userA->id,
            'category_id' => $salary->id,
            'amount' => 100,
            'date' => '2026-07-05',
            'description' => 'Rimborso viaggio',
        ]);
        $income->tags()->attach($tag->id);

        $expense = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->userA->id,
            'category_id' => $food->id,
            'amount' => -40,
            'date' => '2026-07-10',
            'description' => 'Pranzo viaggio',
        ]);
        $expense->tags()->attach($tag->id);

        $uncategorized = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->userA->id,
            'category_id' => null,
            'amount' => -10,
            'date' => '2026-07-12',
            'description' => 'Senza categoria',
        ]);
        $uncategorized->tags()->attach($tag->id);

        $otherTagged = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->userA->id,
            'category_id' => $food->id,
            'amount' => -999,
            'date' => '2026-07-08',
            'description' => 'Altro tag',
        ]);
        $otherTagged->tags()->attach($otherTag->id);

        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->userA->id,
            'category_id' => $food->id,
            'amount' => -50,
            'date' => '2026-07-09',
            'description' => 'Senza tag',
        ]);

        $outsideMonth = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->userA->id,
            'category_id' => $food->id,
            'amount' => -70,
            'date' => '2026-06-20',
            'description' => 'Mese scorso',
        ]);
        $outsideMonth->tags()->attach($tag->id);

        $this->actingAs($this->userA)
            ->get(route('tags.show', ['tag' => $tag, 'month' => '2026-07']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Tags/Show')
                ->where('tag.id', $tag->id)
                ->where('selectedMonth', '2026-07')
                ->where('stats.transaction_count', 3)
                ->where('stats.income', 100)
                ->where('stats.expenses', 50)
                ->where('stats.net', 50)
                ->has('byCategory', 3)
                ->where('byCategory', fn ($rows) => collect($rows)->contains(
                    fn ($row) => $row['category_id'] === null
                        && $row['name'] === 'Senza categoria'
                        && $row['expenses'] == 10
                ))
                ->has('recentTransactions', 3)
            );
    }
}
