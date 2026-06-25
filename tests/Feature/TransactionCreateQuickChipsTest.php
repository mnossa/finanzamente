<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionCreateQuickChipsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function create_page_exposes_quick_chips_from_recent_history(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        $category = Category::factory()->create([
            'household_id' => $household->id,
            'name' => 'Bar',
            'type' => 'expense',
            'icon' => '☕',
            'color' => '#f59e0b',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -2.5,
            'date' => now()->toDateString(),
        ]);

        $response = $this->withoutVite()
            ->actingAs($user)
            ->get(route('transactions.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Transactions/Create')
            ->has('quickChips', 1)
            ->where('quickChips.0.category_id', $category->id)
            ->where('quickChips.0.label', 'Bar')
            ->where('quickChips.0.icon', '☕')
            ->where('quickChips.0.type', 'expense')
            ->where('quickChips.0.account_id', $account->id));
    }

    #[Test]
    public function quick_session_route_is_no_longer_available(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $this->actingAs($user)
            ->get('/transazioni/sessione-rapida')
            ->assertNotFound();
    }
}
