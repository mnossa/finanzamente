<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\PortfolioSnapshotService;
use App\Services\SystemVariableResolver;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PensionFundAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $bankAccount;

    private Account $pensionAccount;

    private Category $expenseCategory;

    private Category $incomeCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'profile_completed' => true,
        ]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->bankAccount = Account::factory()->bank()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 5000,
            'current_balance' => 5000,
        ]);

        $this->pensionAccount = Account::factory()->pensionFund('https://portale.fondo.example')->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 10000,
            'current_balance' => 10000,
            'name' => 'Cometa',
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
    public function user_can_create_pension_fund_account_with_external_url(): void
    {
        $this->actingAs($this->user)
            ->post(route('accounts.store'), [
                'name' => 'PIP Unipol',
                'type' => 'pension_fund',
                'initial_balance' => 2500,
                'currency_code' => 'EUR',
                'external_url' => 'https://area.fondo.test/login',
                'is_private' => false,
            ])
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('accounts', [
            'household_id' => $this->household->id,
            'name' => 'PIP Unipol',
            'type' => 'pension_fund',
            'external_url' => 'https://area.fondo.test/login',
        ]);
    }

    #[Test]
    public function cannot_store_expense_on_pension_fund(): void
    {
        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'account_id' => $this->pensionAccount->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 50,
                'date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('account_id');
    }

    #[Test]
    public function cannot_store_income_on_pension_fund(): void
    {
        $this->actingAs($this->user)
            ->post(route('transactions.store'), [
                'account_id' => $this->pensionAccount->id,
                'category_id' => $this->incomeCategory->id,
                'amount' => 200,
                'date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('account_id');
    }

    #[Test]
    public function can_transfer_from_bank_to_pension_fund(): void
    {
        $this->actingAs($this->user)
            ->post(route('transfers.store'), [
                'source_account_id' => $this->bankAccount->id,
                'destination_account_id' => $this->pensionAccount->id,
                'amount' => 300,
                'date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $balance = app(AccountBalanceService::class)->computeBalance($this->pensionAccount->fresh());
        $this->assertEqualsWithDelta(10300.0, $balance, 0.01);
    }

    #[Test]
    public function can_update_pension_fund_position(): void
    {
        $this->actingAs($this->user)
            ->post(route('accounts.position.update', $this->pensionAccount), [
                'position' => 11250.50,
            ])
            ->assertRedirect(route('accounts.show', $this->pensionAccount));

        $this->pensionAccount->refresh();
        $this->assertEqualsWithDelta(11250.50, (float) $this->pensionAccount->current_balance, 0.01);
        $this->assertEqualsWithDelta(11250.50, (float) $this->pensionAccount->initial_balance, 0.01);
    }

    #[Test]
    public function position_update_preserves_transfer_delta(): void
    {
        $this->actingAs($this->user)
            ->post(route('transfers.store'), [
                'source_account_id' => $this->bankAccount->id,
                'destination_account_id' => $this->pensionAccount->id,
                'amount' => 500,
                'date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->post(route('accounts.position.update', $this->pensionAccount), [
                'position' => 10600,
            ])
            ->assertRedirect(route('accounts.show', $this->pensionAccount));

        $this->pensionAccount->refresh();
        $this->assertEqualsWithDelta(10600.0, (float) $this->pensionAccount->current_balance, 0.01);
        // initial 10000 + transfer 500 → current 10500; set 10600 → initial becomes 10100
        $this->assertEqualsWithDelta(10100.0, (float) $this->pensionAccount->initial_balance, 0.01);
    }

    #[Test]
    public function transaction_create_marks_pension_fund_accounts(): void
    {
        $this->actingAs($this->user)
            ->get(route('transactions.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('accounts')
                ->where('accounts', function ($accounts) {
                    $pension = collect($accounts)->firstWhere('id', $this->pensionAccount->id);

                    return $pension !== null && ($pension['is_pension_fund'] ?? false) === true;
                }));
    }

    #[Test]
    public function portfolio_treats_pension_fund_as_vincolati(): void
    {
        $snapshot = app(PortfolioSnapshotService::class)->build($this->user);

        $this->assertLessThan(5000 + 10000, $snapshot['liquidValue'] + 0.01);
        $this->assertEqualsWithDelta(5000.0, $snapshot['liquidValue'], 0.01);

        $pensionPosition = collect($snapshot['positions'])
            ->firstWhere('id', 'account_'.$this->pensionAccount->id);

        $this->assertNotNull($pensionPosition);
        $this->assertSame('locked', $pensionPosition['asset_class']);
        $this->assertSame('Vincolati', $pensionPosition['asset_class_label']);
    }

    #[Test]
    public function household_balance_and_dashboard_exclude_pension_fund(): void
    {
        $this->assertSame(
            5000.0,
            app(AccountBalanceService::class)->computeHouseholdTotal($this->user),
        );
        $this->assertSame(
            15000.0,
            app(AccountBalanceService::class)->computeHouseholdTotal($this->user, includeLocked: true),
        );

        $today = now();
        $this->assertSame(
            5000.0,
            app(SystemVariableResolver::class)->resolve(
                $this->user,
                'household_balance',
                $today->copy()->startOfDay(),
                $today,
            ),
        );

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('totalBalance', 5000)
                ->where('balanceBreakdown.total', 5000)
                ->where('balanceBreakdown.patrimonioTotal', 15000)
            );
    }
}
