<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpenseDistributionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'profile_completed' => true,
            'profile_settings'  => [],
        ]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role'        => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id'   => $this->household->id,
            'owner_user_id'  => $this->user->id,
            'initial_balance' => 0,
            'active'         => true,
        ]);
    }

    // ─── PUT /dashboard/distribuzione-spese/soglie ────────────────────────

    #[Test]
    public function unauthenticated_user_cannot_update_thresholds(): void
    {
        $this->put(route('expense-distribution.thresholds.update'), [
            'needs'       => 50,
            'wants'       => 30,
            'investments' => 20,
        ])->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_user_can_save_custom_thresholds(): void
    {
        $this->actingAs($this->user)
            ->put(route('expense-distribution.thresholds.update'), [
                'needs'       => 60,
                'wants'       => 25,
                'investments' => 15,
            ])
            ->assertRedirect();

        $this->user->refresh();
        $thresholds = $this->user->profile_settings['expense_distribution_thresholds'];

        $this->assertEquals(60, $thresholds['needs']);
        $this->assertEquals(25, $thresholds['wants']);
        $this->assertEquals(15, $thresholds['investments']);
    }

    #[Test]
    public function threshold_values_must_be_numeric_between_0_and_100(): void
    {
        $this->actingAs($this->user)
            ->put(route('expense-distribution.thresholds.update'), [
                'needs'       => 150,
                'wants'       => -5,
                'investments' => 'abc',
            ])
            ->assertSessionHasErrors(['needs', 'wants', 'investments']);
    }

    #[Test]
    public function authenticated_user_can_reset_thresholds_to_default(): void
    {
        // Prima salviamo soglie personalizzate
        $this->user->update([
            'profile_settings' => [
                'expense_distribution_thresholds' => [
                    'needs'       => 60,
                    'wants'       => 25,
                    'investments' => 15,
                ],
            ],
        ]);

        $this->actingAs($this->user)
            ->delete(route('expense-distribution.thresholds.reset'))
            ->assertRedirect();

        $this->user->refresh();
        $this->assertArrayNotHasKey(
            'expense_distribution_thresholds',
            $this->user->profile_settings ?? []
        );
    }

    // ─── Dashboard — dati distribuzione spese ─────────────────────────────

    #[Test]
    public function dashboard_includes_expense_distribution_data(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->has('expenseDistributionData')
                 ->has('expenseDistributionData.needs')
                 ->has('expenseDistributionData.wants')
                 ->has('expenseDistributionData.investments')
                 ->has('expenseDistributionData.unclassified')
                 ->has('expenseDistributionData.total_expenses')
                 ->has('expenseDistributionData.thresholds')
        );
    }

    #[Test]
    public function expense_distribution_groups_transactions_by_category_type(): void
    {
        // Crea categorie classificate
        $catNeeds = Category::factory()->create([
            'household_id'         => $this->household->id,
            'type'                 => 'expense',
            'expense_distribution' => 'needs',
        ]);
        $catWants = Category::factory()->create([
            'household_id'         => $this->household->id,
            'type'                 => 'expense',
            'expense_distribution' => 'wants',
        ]);
        $catUnclassified = Category::factory()->create([
            'household_id'         => $this->household->id,
            'type'                 => 'expense',
            'expense_distribution' => null,
        ]);

        // Aggiunge transazioni nel mese corrente
        Transaction::factory()->create([
            'account_id'  => $this->account->id,
            'user_id'     => $this->user->id,
            'category_id' => $catNeeds->id,
            'amount'      => -500,
            'date'        => Carbon::now()->startOfMonth()->addDays(2),
        ]);
        Transaction::factory()->create([
            'account_id'  => $this->account->id,
            'user_id'     => $this->user->id,
            'category_id' => $catWants->id,
            'amount'      => -200,
            'date'        => Carbon::now()->startOfMonth()->addDays(3),
        ]);
        Transaction::factory()->create([
            'account_id'  => $this->account->id,
            'user_id'     => $this->user->id,
            'category_id' => $catUnclassified->id,
            'amount'      => -300,
            'date'        => Carbon::now()->startOfMonth()->addDays(4),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->where('expenseDistributionData.total_expenses', 1000)
                 ->where('expenseDistributionData.needs.amount', 500)
                 ->where('expenseDistributionData.wants.amount', 200)
                 ->where('expenseDistributionData.unclassified.amount', 300)
        );
    }

    #[Test]
    public function expense_distribution_uses_custom_thresholds_when_set(): void
    {
        $this->user->update([
            'profile_settings' => [
                'expense_distribution_thresholds' => [
                    'needs'       => 40,
                    'wants'       => 40,
                    'investments' => 20,
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertInertia(fn ($page) =>
            $page->where('expenseDistributionData.thresholds.needs', 40)
                 ->where('expenseDistributionData.thresholds.wants', 40)
                 ->where('expenseDistributionData.has_custom_thresholds', true)
        );
    }

    #[Test]
    public function expense_distribution_uses_default_thresholds_when_not_customized(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertInertia(fn ($page) =>
            $page->where('expenseDistributionData.thresholds.needs', 50)
                 ->where('expenseDistributionData.thresholds.wants', 30)
                 ->where('expenseDistributionData.thresholds.investments', 20)
                 ->where('expenseDistributionData.has_custom_thresholds', false)
        );
    }

    #[Test]
    public function expense_distribution_ignores_income_transactions(): void
    {
        $catNeeds = Category::factory()->create([
            'household_id'         => $this->household->id,
            'type'                 => 'income',
            'expense_distribution' => 'needs',
        ]);

        Transaction::factory()->create([
            'account_id'  => $this->account->id,
            'user_id'     => $this->user->id,
            'category_id' => $catNeeds->id,
            'amount'      => 1000, // entrata
            'date'        => Carbon::now()->startOfMonth()->addDays(1),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertInertia(fn ($page) =>
            $page->where('expenseDistributionData.total_expenses', 0)
        );
    }

    #[Test]
    public function exceeded_flag_is_true_when_percentage_exceeds_threshold(): void
    {
        $catWants = Category::factory()->create([
            'household_id'         => $this->household->id,
            'type'                 => 'expense',
            'expense_distribution' => 'wants',
        ]);

        // wants = 80% delle spese totali, soglia wants = 30%
        Transaction::factory()->create([
            'account_id'  => $this->account->id,
            'user_id'     => $this->user->id,
            'category_id' => $catWants->id,
            'amount'      => -800,
            'date'        => Carbon::now()->startOfMonth()->addDays(1),
        ]);
        $catNeeds = Category::factory()->create([
            'household_id'         => $this->household->id,
            'type'                 => 'expense',
            'expense_distribution' => 'needs',
        ]);
        Transaction::factory()->create([
            'account_id'  => $this->account->id,
            'user_id'     => $this->user->id,
            'category_id' => $catNeeds->id,
            'amount'      => -200,
            'date'        => Carbon::now()->startOfMonth()->addDays(2),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('dashboard'));

        $response->assertInertia(fn ($page) =>
            $page->where('expenseDistributionData.wants.exceeded', true)
                 ->where('expenseDistributionData.needs.exceeded', false)
        );
    }
}
