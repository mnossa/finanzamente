<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Category;
use App\Models\FinancialVariable;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FormulaResolverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private FormulaResolverService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FormulaResolverService::class);
        $this->user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $this->user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $this->user->id,
        ]);

        $incomeCategory = Category::factory()->create([
            'household_id' => $household->id,
            'type' => 'income',
        ]);

        $expenseCategory = Category::factory()->create([
            'household_id' => $household->id,
            'type' => 'expense',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $incomeCategory->id,
            'amount' => 2000,
            'date' => now()->startOfMonth(),
            'description' => 'Stipendio',
            'currency_code' => 'EUR',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'category_id' => $expenseCategory->id,
            'amount' => -500,
            'date' => now()->startOfMonth(),
            'description' => 'Spesa',
            'currency_code' => 'EUR',
        ]);
    }

    #[Test]
    public function it_evaluates_static_and_formula_variables(): void
    {
        FinancialVariable::factory()->for($this->user)->create([
            'code' => 'custom_tax',
            'name' => 'Custom Tax',
            'static_value' => 100,
        ]);

        FinancialVariable::factory()->for($this->user)->formula('[custom_tax] * 2')->create([
            'code' => 'tax_doubled',
            'name' => 'Tax Doubled',
        ]);

        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfDay();

        $this->assertSame(200.0, $this->service->resolveCode($this->user, 'tax_doubled', $start, $end));
    }

    #[Test]
    public function it_resolves_system_variables_for_authenticated_user_only(): void
    {
        $otherUser = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $otherUser->id]);
        $otherUser->update(['active_household_id' => $household->id]);

        FinancialVariable::factory()->for($otherUser)->create([
            'code' => 'secret_var',
            'name' => 'Secret',
            'static_value' => 999,
        ]);

        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfDay();

        $this->expectException(ValidationException::class);
        $this->service->resolveCode($this->user, 'secret_var', $start, $end);
    }

    #[Test]
    public function it_evaluates_formula_with_system_variables(): void
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfDay();

        $gross = $this->service->resolveCode($this->user, 'total_income', $start, $end);

        $this->assertSame(2000.0, $gross);
    }

    #[Test]
    public function it_returns_monthly_series_buckets(): void
    {
        $start = Carbon::now()->subMonths(2)->startOfMonth();
        $end = Carbon::now()->endOfDay();

        $series = $this->service->evaluateMonthlySeries($this->user, 'period_income', $start, $end);

        $this->assertNotEmpty($series);
        $this->assertArrayHasKey('label', $series[0]);
        $this->assertArrayHasKey('value', $series[0]);
    }

    #[Test]
    public function it_evaluates_formula_with_context_variables(): void
    {
        FinancialVariable::factory()->for($this->user)->formula(
            '[days_elapsed_in_month] * 10',
        )->create([
            'code' => 'giorni_per_dieci',
            'name' => 'Giorni moltiplicati',
        ]);

        $start = Carbon::create(2026, 6, 1)->startOfDay();
        $end = Carbon::create(2026, 6, 10)->endOfDay();

        $value = $this->service->resolveCode($this->user, 'giorni_per_dieci', $start, $end);

        $this->assertSame(100.0, $value);
    }

    #[Test]
    public function it_resolves_context_system_code(): void
    {
        $start = Carbon::create(2026, 6, 1)->startOfDay();
        $end = Carbon::create(2026, 6, 10)->endOfDay();

        $this->assertSame(6.0, $this->service->resolveCode($this->user, 'current_month', $start, $end));
        $this->assertSame(10.0, $this->service->resolveCode($this->user, 'days_in_period', $start, $end));
    }

    #[Test]
    public function it_detects_runtime_cycles(): void
    {
        FinancialVariable::factory()->for($this->user)->formula('[var_b]')->create(['code' => 'var_a', 'name' => 'A']);
        FinancialVariable::factory()->for($this->user)->formula('[var_a]')->create(['code' => 'var_b', 'name' => 'B']);

        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfDay();

        $this->expectException(ValidationException::class);
        $this->service->resolveCode($this->user, 'var_a', $start, $end);
    }
}
