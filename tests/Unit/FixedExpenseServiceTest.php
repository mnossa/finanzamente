<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FixedExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FixedExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private FixedExpenseService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FixedExpenseService::class);

        Currency::create([
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
        ]);
    }

    #[Test]
    public function it_returns_error_for_shared_wallet_household()
    {
        $owner = User::factory()->create();

        $household = Household::create([
            'name' => 'Shared Household',
            'owner_user_id' => $owner->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $household->users()->attach($owner->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);

        $result = $this->service->calculateFixedExpenseContributions($household);

        $this->assertNotNull($result['error']);
        $this->assertSame([], $result['contributions']);
    }

    #[Test]
    public function it_calculates_contributions_with_custom_percentages()
    {
        [$household, $owner, $member, $fixedCategory] = $this->createDebtBalancingFixture();

        $household->setBalancePercentages([
            $owner->id => 70,
            $member->id => 30,
        ]);
        $household->save();

        $ownerAccount = Account::create([
            'household_id' => $household->id,
            'name' => 'Owner account',
            'type' => 'bank',
            'initial_balance' => 0,
            'current_balance' => 0,
            'currency_code' => 'EUR',
            'owner_user_id' => $owner->id,
        ]);

        $memberAccount = Account::create([
            'household_id' => $household->id,
            'name' => 'Member account',
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
            'amount' => -100,
            'currency_code' => 'EUR',
            'date' => now()->toDateString(),
        ]);

        $result = $this->service->calculateFixedExpenseContributions($household);

        $this->assertNull($result['error']);
        $this->assertEquals(100, $result['total_household_expenses']);
        $this->assertEquals(1, $result['fixed_categories_count']);
        $this->assertEquals(100, $result['contributions'][$owner->id]['total_contributed']);
        $this->assertEquals(70, $result['contributions'][$owner->id]['expected_contribution']);
        $this->assertEquals(30, $result['contributions'][$owner->id]['balance']);
        $this->assertEquals(0, $result['contributions'][$member->id]['total_contributed']);
        $this->assertEquals(30, $result['contributions'][$member->id]['expected_contribution']);
        $this->assertEquals(-30, $result['contributions'][$member->id]['balance']);
    }

    #[Test]
    public function it_suggests_turns_in_round_robin_when_enabled()
    {
        [$household, $owner, $member, $fixedCategory] = $this->createDebtBalancingFixture();

        $household->update([
            'enable_turn_suggestions' => true,
        ]);

        $firstSuggestion = $this->service->suggestNextTurnForCategory($household, $fixedCategory->id);
        $this->assertNull($firstSuggestion['error']);
        $firstUserId = $firstSuggestion['suggestion']['user_id'];

        $saved = $this->service->registerTurnCompleted($household, $fixedCategory->id, $firstUserId);
        $this->assertTrue($saved);

        $secondSuggestion = $this->service->suggestNextTurnForCategory($household->fresh(), $fixedCategory->id);
        $this->assertNull($secondSuggestion['error']);
        $secondUserId = $secondSuggestion['suggestion']['user_id'];

        $this->assertNotSame($firstUserId, $secondUserId);
        $this->assertContains($secondUserId, [$owner->id, $member->id]);
    }

    #[Test]
    public function it_does_not_register_turn_when_suggestions_are_disabled()
    {
        [$household, $owner, $member, $fixedCategory] = $this->createDebtBalancingFixture();

        $saved = $this->service->registerTurnCompleted($household, $fixedCategory->id, $owner->id);

        $this->assertFalse($saved);
        $this->assertNull($household->fresh()->getLastTurnAssignment($fixedCategory->id));
    }

    private function createDebtBalancingFixture(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $household = Household::create([
            'name' => 'Debt Household',
            'owner_user_id' => $owner->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
            'enable_turn_suggestions' => false,
        ]);

        $household->users()->attach([
            $owner->id => [
                'role' => 'owner',
                'permissions' => json_encode(['manage' => true]),
            ],
            $member->id => [
                'role' => 'member',
                'permissions' => json_encode([]),
            ],
        ]);

        $fixedCategory = Category::create([
            'household_id' => $household->id,
            'name' => 'Affitto',
            'type' => 'expense',
            'is_fixed_expense' => true,
        ]);

        return [$household, $owner, $member, $fixedCategory];
    }
}