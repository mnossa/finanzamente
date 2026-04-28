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

class FixedExpensesFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    #[Test]
    public function debt_balancing_member_can_access_fixed_expenses_dashboard()
    {
        [$household, $owner, $member] = $this->createHouseholdWithOwnerAndMember('debt_balancing');

        $member->update(['active_household_id' => $household->id]);

        $response = $this->actingAs($member)->get(route('fixed-expenses.dashboard', $household));

        $response->assertOk();
    }

    #[Test]
    public function shared_wallet_household_is_redirected_from_fixed_expenses_dashboard()
    {
        [$household, $owner] = $this->createHouseholdWithOwnerAndMember('shared_wallet');

        $owner->update(['active_household_id' => $household->id]);

        $response = $this->actingAs($owner)->get(route('fixed-expenses.dashboard', $household));

        $response
            ->assertRedirect(route('households.show', $household))
            ->assertSessionHas('error');
    }

    #[Test]
    public function contributions_endpoint_returns_expected_totals_and_balances()
    {
        [$household, $owner, $member] = $this->createHouseholdWithOwnerAndMember('debt_balancing');

        $owner->update(['active_household_id' => $household->id]);

        // Usa una delle categorie fisse create automaticamente dall'Observer
        $fixedCategory = Category::where([
            'household_id' => $household->id,
            'name' => 'Affitto',
        ])->first();

        $ownerAccount = Account::create([
            'household_id' => $household->id,
            'name' => 'Conto Owner',
            'type' => 'bank',
            'initial_balance' => 0,
            'current_balance' => 0,
            'currency_code' => 'EUR',
            'owner_user_id' => $owner->id,
        ]);

        $memberAccount = Account::create([
            'household_id' => $household->id,
            'name' => 'Conto Member',
            'type' => 'bank',
            'initial_balance' => 0,
            'current_balance' => 0,
            'currency_code' => 'EUR',
            'owner_user_id' => $member->id,
        ]);

        Transaction::create([
            'user_id' => $owner->id,
            'account_id' => $ownerAccount->id,
            'category_id' => $fixedCategory->id,
            'amount' => -70,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
            'description' => 'Affitto owner',
        ]);

        Transaction::create([
            'user_id' => $member->id,
            'account_id' => $memberAccount->id,
            'category_id' => $fixedCategory->id,
            'amount' => -30,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
            'description' => 'Affitto member',
        ]);

        Transaction::create([
            'user_id' => $owner->id,
            'account_id' => $ownerAccount->id,
            'category_id' => $fixedCategory->id,
            'amount' => 50,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
            'description' => 'Operazione positiva ignorata',
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('fixed-expenses.contributions', $household));

        $response
            ->assertOk()
            ->assertJsonPath('error', null)
            ->assertJsonPath('total_household_expenses', 100)
            ->assertJsonPath('fixed_categories_count', 11)
            ->assertJsonPath("contributions.{$owner->id}.total_contributed", 70)
            ->assertJsonPath("contributions.{$member->id}.total_contributed", 30)
            ->assertJsonPath("contributions.{$owner->id}.expected_contribution", 50)
            ->assertJsonPath("contributions.{$member->id}.expected_contribution", 50)
            ->assertJsonPath("contributions.{$owner->id}.balance", 20)
            ->assertJsonPath("contributions.{$member->id}.balance", -20);
    }

    #[Test]
    public function owner_can_complete_turn_and_store_last_assignment()
    {
        [$household, $owner, $member] = $this->createHouseholdWithOwnerAndMember('debt_balancing', true);

        $owner->update(['active_household_id' => $household->id]);

        // Usa una delle categorie fisse create automaticamente dall'Observer
        $fixedCategory = Category::where([
            'household_id' => $household->id,
            'name' => 'Bollette',
        ])->first();

        $response = $this->actingAs($owner)->post(
            route('fixed-expenses.complete-turn', ['household' => $household, 'category' => $fixedCategory]),
            ['user_id' => $member->id]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $household->refresh();
        $this->assertSame($member->id, $household->getLastTurnAssignment($fixedCategory->id));
    }

    #[Test]
    public function guest_cannot_complete_turn_because_route_requires_modify_permissions()
    {
        [$household, $owner, $member] = $this->createHouseholdWithOwnerAndMember('debt_balancing', true);
        $guest = User::factory()->create();

        $household->users()->attach($guest->id, [
            'role' => 'guest',
            'permissions' => json_encode(['view_only' => true]),
        ]);
        $guest->update(['active_household_id' => $household->id]);

        // Usa una delle categorie fisse create automaticamente dall'Observer
        $fixedCategory = Category::where([
            'household_id' => $household->id,
            'name' => 'Bollette',
        ])->first();

        $response = $this->actingAs($guest)->post(
            route('fixed-expenses.complete-turn', ['household' => $household, 'category' => $fixedCategory]),
            ['user_id' => $member->id]
        );

        $response->assertStatus(403);
    }

    #[Test]
    public function non_owner_cannot_update_turn_settings()
    {
        [$household, $owner, $member] = $this->createHouseholdWithOwnerAndMember('debt_balancing');

        $member->update(['active_household_id' => $household->id]);

        $response = $this->actingAs($member)->patch(
            route('fixed-expenses.update-turn-settings', ['household' => $household]),
            ['enable_turn_suggestions' => true]
        );

        $response->assertStatus(403);
    }

    private function createHouseholdWithOwnerAndMember(string $financialType, bool $turnSuggestionsEnabled = false): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $household = Household::create([
            'name' => 'Casa Test',
            'owner_user_id' => $owner->id,
            'financial_management_type' => $financialType,
            'enable_turn_suggestions' => $turnSuggestionsEnabled,
        ]);

        $household->users()->attach([
            $owner->id => [
                'role' => 'owner',
                'permissions' => json_encode(['manage' => true, 'supervise' => true]),
            ],
            $member->id => [
                'role' => 'member',
                'permissions' => json_encode([]),
            ],
        ]);

        return [$household, $owner, $member];
    }
}
