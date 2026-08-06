<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CohortInsights\CohortInsightSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CohortInsightSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_bucket_wants_share_percent_rounds_to_five(): void
    {
        $b = new CohortInsightSnapshotBuilder;

        $this->assertSame(50, $b->bucketWantsSharePercent(0.5));
        $this->assertSame(0, $b->bucketWantsSharePercent(0.02));
        $this->assertSame(100, $b->bucketWantsSharePercent(1.0));
        $this->assertSame(35, $b->bucketWantsSharePercent(0.34));
    }

    public function test_build_snapshot_excludes_prefer_not_income_band(): void
    {
        $user = $this->makeUserWithHousehold(['income_band' => 'prefer_not']);
        $this->seedClassifiedSpending($user);

        $b = new CohortInsightSnapshotBuilder;
        $out = $b->buildForPeriod(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));

        $this->assertSame([], $out['rows']);
    }

    public function test_build_snapshot_includes_bucketed_row(): void
    {
        $user = $this->makeUserWithHousehold(['income_band' => '35k_50k', 'macro_region' => 'centro']);
        $this->seedClassifiedSpending($user);

        $b = new CohortInsightSnapshotBuilder;
        $out = $b->buildForPeriod(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));

        $this->assertCount(1, $out['rows']);
        $row = $out['rows'][0];
        $this->assertArrayHasKey('subject_ref', $row);
        $this->assertSame('35k_50k', $row['income_band']);
        $this->assertSame('centro', $row['macro_region']);
        $this->assertSame(50, $row['wants_share_pct_bucket']);
        $this->assertSame($user->id, $out['subject_to_user_id'][$row['subject_ref']]);
    }

    /**
     * @param  array<string, mixed>  $userOverrides
     */
    private function makeUserWithHousehold(array $userOverrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'profile_completed' => true,
        ], $userOverrides));

        $household = Household::create([
            'name' => 'HH Test',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'supervise' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        return $user->fresh();
    }

    private function seedClassifiedSpending(User $user): void
    {
        $account = Account::create([
            'household_id' => $user->active_household_id,
            'owner_user_id' => $user->id,
            'name' => 'Conto',
            'type' => 'bank',
            'currency_code' => 'EUR',
            'initial_balance' => 0,
            'active' => true,
        ]);

        $needs = Category::factory()->expense()->create([
            'household_id' => $user->active_household_id,
            'name' => 'Bisogni',
            'expense_distribution' => Category::DISTRIBUTION_NEEDS,
        ]);
        $wants = Category::factory()->expense()->create([
            'household_id' => $user->active_household_id,
            'name' => 'Extra',
            'expense_distribution' => Category::DISTRIBUTION_WANTS,
        ]);

        foreach ([
            [$needs->id, -500],
            [$wants->id, -500],
        ] as [$catId, $amount]) {
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'category_id' => $catId,
                'amount' => $amount,
                'currency_code' => 'EUR',
                'exchange_rate_to_base' => 1,
                'amount_base' => abs($amount),
                'date' => '2026-04-15',
                'is_private' => false,
            ]);
        }
    }
}
