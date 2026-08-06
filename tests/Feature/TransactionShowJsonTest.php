<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionShowJsonTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function show_returns_json_when_requested(): void
    {
        [$user, $tx] = $this->createUserWithTransaction();

        $response = $this->actingAs($user)->getJson("/transazioni/{$tx->id}");

        $response->assertOk()
            ->assertJsonPath('transaction.id', $tx->id)
            ->assertJsonPath('transaction.description', 'Spesa test slide-over')
            ->assertJsonStructure([
                'transaction' => [
                    'id',
                    'amount',
                    'date',
                    'description',
                    'account',
                    'category',
                    'user',
                    'tags',
                ],
                'indexQueryForReturn',
            ]);
    }

    #[Test]
    public function show_still_renders_inertia_for_html(): void
    {
        [$user, $tx] = $this->createUserWithTransaction();

        $this->actingAs($user)
            ->get("/transazioni/{$tx->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transactions/Show')
                ->where('transaction.id', $tx->id));
    }

    /**
     * @return array{0: User, 1: Transaction}
     */
    private function createUserWithTransaction(): array
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
        $category = Category::factory()->expense()->create([
            'household_id' => $household->id,
        ]);

        $tx = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -25.50,
            'currency_code' => 'EUR',
            'date' => '2026-07-01',
            'description' => 'Spesa test slide-over',
        ]);

        return [$user, $tx];
    }
}
