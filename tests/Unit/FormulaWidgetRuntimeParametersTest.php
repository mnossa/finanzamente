<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DebtCredit;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardPeriodStatsService;
use App\Services\DebtCreditTransactionPrefillService;
use App\Services\FormulaPeriodResolver;
use App\Services\FormulaWidgetParameterService;
use App\Services\FormulaWidgetPayloadBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetRuntimeParametersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $accountA;

    private Account $accountB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->user->active_household_id = $this->household->id;
        $this->user->save();

        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€']);

        $this->accountA = Account::create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Conto A',
            'currency_code' => 'EUR',
            'initial_balance' => 1000,
            'current_balance' => 1000,
            'active' => true,
            'is_private' => false,
        ]);

        $this->accountB = Account::create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Conto B',
            'currency_code' => 'EUR',
            'initial_balance' => 500,
            'current_balance' => 500,
            'active' => true,
            'is_private' => false,
        ]);

        $categoryIncome = Category::create([
            'household_id' => $this->household->id,
            'name' => 'Stipendio',
            'type' => 'income',
            'color' => '#00AA00',
        ]);

        $categoryExpense = Category::create([
            'household_id' => $this->household->id,
            'name' => 'Spesa',
            'type' => 'expense',
            'color' => '#AA0000',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->accountA->id,
            'category_id' => $categoryIncome->id,
            'amount' => 300,
            'currency_code' => 'EUR',
            'date' => Carbon::today(),
            'description' => 'Entrata A',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->accountA->id,
            'category_id' => $categoryExpense->id,
            'amount' => -100,
            'currency_code' => 'EUR',
            'date' => Carbon::today(),
            'description' => 'Uscita A',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->accountB->id,
            'category_id' => $categoryIncome->id,
            'amount' => 50,
            'currency_code' => 'EUR',
            'date' => Carbon::today(),
            'description' => 'Entrata B',
        ]);
    }

    #[Test]
    public function period_stats_can_be_filtered_by_account(): void
    {
        $service = app(DashboardPeriodStatsService::class);
        $start = Carbon::today()->startOfDay();
        $end = Carbon::today()->endOfDay();

        $householdStats = $service->calculate($this->user, $start, $end);
        $accountAStats = $service->calculate($this->user, $start, $end, $this->accountA->id);

        $this->assertSame(350.0, $householdStats['income']);
        $this->assertSame(100.0, $householdStats['expenses']);
        $this->assertSame(300.0, $accountAStats['income']);
        $this->assertSame(100.0, $accountAStats['expenses']);
        $this->assertSame(200.0, $accountAStats['net']);
    }

    #[Test]
    public function formula_widget_payload_includes_account_parameter_metadata(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[period_net]')->create();

        $widget = FormulaWidget::make([
            'user_id' => $this->user->id,
            'financial_variable_id' => $variable->id,
            'name' => 'Riepilogo conto',
            'display_type' => 'bar',
            'period_preset' => 'current_month',
            'chart_config' => [
                'parameters' => [
                    ['key' => 'account_id', 'type' => 'account', 'label' => 'Conto', 'default' => 'all'],
                ],
                'series' => [
                    ['code' => 'period_income', 'label' => 'Incassato'],
                    ['code' => 'period_expenses', 'label' => 'Speso'],
                    ['code' => 'period_net', 'label' => 'Risparmiato'],
                ],
            ],
        ]);
        $widget->setRelation('financialVariable', $variable);

        $payload = app(FormulaWidgetPayloadBuilder::class)->build(
            $widget,
            $this->user,
            ['account_id' => (string) $this->accountA->id],
        );

        $this->assertSame('bar', $payload['type']);
        $this->assertArrayHasKey('parameters', $payload);
        $this->assertSame((string) $this->accountA->id, $payload['parameters'][0]['value']);
        $this->assertGreaterThan(0, count($payload['categories']));
    }

    #[Test]
    public function period_navigation_resolves_previous_month_window(): void
    {
        $resolver = app(FormulaPeriodResolver::class);

        $current = $resolver->resolve('current_month', $this->user);
        $previous = $resolver->resolveWithOffset('current_month', $this->user, -1);

        $expectedStart = Carbon::now()->startOfMonth()->subMonth();

        $this->assertTrue($previous['end']->lt($current['start']));
        $this->assertSame($expectedStart->toDateString(), $previous['start']->toDateString());
        $this->assertSame($expectedStart->copy()->endOfMonth()->toDateString(), $previous['end']->toDateString());
    }

    #[Test]
    public function formula_widget_payload_includes_period_nav_metadata(): void
    {
        $variable = FinancialVariable::factory()->for($this->user)->formula('[period_net]')->create();

        $widget = FormulaWidget::make([
            'user_id' => $this->user->id,
            'financial_variable_id' => $variable->id,
            'name' => 'Riepilogo conto',
            'display_type' => 'bar',
            'period_preset' => 'current_month',
            'chart_config' => [
                'parameters' => [
                    ['key' => 'account_id', 'type' => 'account', 'label' => 'Conto', 'default' => 'all'],
                    ['key' => 'period_offset', 'type' => 'period_nav', 'label' => 'Mese', 'default' => '0'],
                ],
                'series' => [
                    ['code' => 'period_income', 'label' => 'Incassato'],
                    ['code' => 'period_expenses', 'label' => 'Speso'],
                    ['code' => 'period_net', 'label' => 'Risparmiato'],
                ],
            ],
        ]);
        $widget->setRelation('financialVariable', $variable);

        $payload = app(FormulaWidgetPayloadBuilder::class)->build(
            $widget,
            $this->user,
            ['period_offset' => '-1'],
        );

        $periodNav = collect($payload['parameters'])->firstWhere('type', 'period_nav');

        $this->assertNotNull($periodNav);
        $this->assertSame('-1', $periodNav['value']);
        $this->assertSame(FormulaWidgetParameterService::PERIOD_NAV_MIN_OFFSET, $periodNav['min']);
        $this->assertSame(FormulaWidgetParameterService::PERIOD_NAV_MAX_OFFSET, $periodNav['max']);
        $this->assertSame($payload['periodLabel'], $periodNav['display_label']);
    }

    #[Test]
    public function period_nav_offset_is_clamped_to_supported_range(): void
    {
        $service = app(FormulaWidgetParameterService::class);
        $config = [
            'parameters' => [
                ['key' => 'period_offset', 'type' => 'period_nav', 'label' => 'Mese', 'default' => '0'],
            ],
        ];

        $tooFarBack = $service->resolveValues($this->user, $config, ['period_offset' => '-999']);
        $future = $service->resolveValues($this->user, $config, ['period_offset' => '5']);

        $this->assertSame((string) FormulaWidgetParameterService::PERIOD_NAV_MIN_OFFSET, $tooFarBack['period_offset']);
        $this->assertSame((string) FormulaWidgetParameterService::PERIOD_NAV_MAX_OFFSET, $future['period_offset']);
    }

    #[Test]
    public function public_widgets_cannot_include_account_parameters(): void
    {
        $this->expectException(ValidationException::class);

        app(FormulaWidgetParameterService::class)->validateChartConfig([
            'parameters' => [
                ['key' => 'account_id', 'type' => 'account', 'label' => 'Conto', 'default' => 'all'],
            ],
        ], true);
    }

    #[Test]
    public function debt_credit_transaction_prefill_uses_latest_transaction_context(): void
    {
        $category = Category::create([
            'household_id' => $this->household->id,
            'name' => 'Prestito',
            'type' => 'expense',
            'color' => '#333333',
        ]);

        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Banca Test',
            'amount' => 1000,
            'initial_amount' => 1000,
            'paid_amount' => 200,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
            'description' => 'Mutuo casa',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->accountA->id,
            'category_id' => $category->id,
            'amount' => -200,
            'currency_code' => 'EUR',
            'date' => Carbon::today()->subDay(),
            'description' => 'Rata precedente',
            'debt_credit_id' => $debt->id,
        ]);

        $prefill = app(DebtCreditTransactionPrefillService::class)->build($this->user, $debt->id);

        $this->assertNotNull($prefill);
        $this->assertSame((string) $debt->id, $prefill['debt_credit_id']);
        $this->assertSame('expense', $prefill['transaction_type']);
        $this->assertSame((string) $category->id, $prefill['category_id']);
        $this->assertSame((string) $this->accountA->id, $prefill['account_id']);
        $this->assertSame('800.00', $prefill['amount']);
        $this->assertStringContainsString('Banca Test', $prefill['description']);
    }
}
