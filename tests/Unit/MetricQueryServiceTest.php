<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Household;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FormulaSyntaxValidator;
use App\Services\FormulaWidgets\MetricQueryService;
use App\Services\FormulaWidgets\MetricQueryValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetricQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private Tag $tag;

    private Category $excludedCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->user->update(['active_household_id' => $this->household->id]);

        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€']);

        $this->account = Account::create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Conto test',
            'currency_code' => 'EUR',
            'initial_balance' => 0,
            'current_balance' => 0,
            'active' => true,
            'is_private' => false,
        ]);

        $includedCategory = Category::create([
            'household_id' => $this->household->id,
            'name' => 'Spesa',
            'type' => 'expense',
            'color' => '#AA0000',
        ]);

        $this->excludedCategory = Category::create([
            'household_id' => $this->household->id,
            'name' => 'Esclusa',
            'type' => 'expense',
            'color' => '#333333',
        ]);

        $this->tag = Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'VIAGGIO',
            'color' => '#00AAFF',
        ]);

        $includedTx = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $includedCategory->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'amount_base' => -50,
            'exchange_rate_to_base' => 1,
            'date' => Carbon::today(),
            'description' => 'Con tag',
        ]);
        $includedTx->tags()->attach($this->tag->id);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->excludedCategory->id,
            'amount' => -80,
            'currency_code' => 'EUR',
            'amount_base' => -80,
            'exchange_rate_to_base' => 1,
            'date' => Carbon::today(),
            'description' => 'Tag ma categoria esclusa',
        ])->tags()->attach($this->tag->id);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $includedCategory->id,
            'amount' => -20,
            'currency_code' => 'EUR',
            'amount_base' => -20,
            'exchange_rate_to_base' => 1,
            'date' => Carbon::today(),
            'description' => 'Senza tag',
        ]);
    }

    #[Test]
    public function it_counts_transactions_with_runtime_tag_filter(): void
    {
        $service = app(MetricQueryService::class);
        $metricQuery = [
            'datasource' => 'transactions',
            'measure' => 'count',
            'amount_field' => 'amount_base',
            'filters' => [
                ['field' => 'tag', 'operator' => 'in', 'runtime_key' => 'tag_selected'],
            ],
        ];

        $count = $service->evaluate(
            $this->user,
            $metricQuery,
            Carbon::today()->startOfDay(),
            Carbon::today()->endOfDay(),
            ['tag_selected' => (string) $this->tag->id],
        );

        $this->assertSame(2.0, $count);
    }

    #[Test]
    public function it_excludes_category_from_tagged_transactions(): void
    {
        $service = app(MetricQueryService::class);
        $metricQuery = [
            'datasource' => 'transactions',
            'measure' => 'count',
            'amount_field' => 'amount_base',
            'filters' => [
                ['field' => 'tag', 'operator' => 'in', 'runtime_key' => 'tag_selected'],
                ['field' => 'category', 'operator' => 'not_in', 'runtime_key' => 'category_excluded'],
            ],
        ];

        $count = $service->evaluate(
            $this->user,
            $metricQuery,
            Carbon::today()->startOfDay(),
            Carbon::today()->endOfDay(),
            [
                'tag_selected' => (string) $this->tag->id,
                'category_excluded' => (string) $this->excludedCategory->id,
            ],
        );

        $this->assertSame(1.0, $count);
    }

    #[Test]
    public function metric_query_validator_accepts_valid_configuration(): void
    {
        app(MetricQueryValidator::class)->validate([
            'datasource' => 'transactions',
            'measure' => 'count',
            'filters' => [
                ['field' => 'tag', 'operator' => 'in', 'runtime_key' => 'tag_selected'],
            ],
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function metric_query_validator_rejects_invalid_measure(): void
    {
        $this->expectException(ValidationException::class);

        app(MetricQueryValidator::class)->validate([
            'datasource' => 'transactions',
            'measure' => 'invalid_measure',
            'filters' => [],
        ]);
    }

    #[Test]
    public function formula_syntax_validator_accepts_if_and_when(): void
    {
        $validator = app(FormulaSyntaxValidator::class);

        $validator->validate($this->user, 'IF([period_expenses] > 1000, 1, 0)');
        $validator->validate($this->user, 'WHEN([period_net] > 0, [period_income])');

        $this->assertSame(1.0, $validator->evaluateNumericExpression('IF(1500 > 1000, 1, 0)'));
        $this->assertSame(0.0, $validator->evaluateNumericExpression('WHEN(0 > 1, 99)'));
    }

    #[Test]
    public function it_lists_transaction_rows_for_table_widgets(): void
    {
        $service = app(MetricQueryService::class);
        $rows = $service->listRows(
            $this->user,
            [
                'datasource' => 'transactions',
                'measure' => 'count',
                'amount_field' => 'amount_base',
                'filters' => [],
            ],
            Carbon::today()->startOfDay(),
            Carbon::today()->endOfDay(),
            [],
            null,
            10,
            ['field' => 'date', 'direction' => 'desc'],
        );

        $this->assertGreaterThanOrEqual(3, count($rows));
        $this->assertArrayHasKey('date', $rows[0]);
        $this->assertArrayHasKey('amount', $rows[0]);
    }

    #[Test]
    public function it_aggregates_transactions_by_category(): void
    {
        $service = app(MetricQueryService::class);
        $groups = $service->aggregateTable(
            $this->user,
            [
                'datasource' => 'transactions',
                'measure' => 'sum_abs',
                'amount_field' => 'amount_base',
                'filters' => [
                    ['field' => 'transaction_type', 'operator' => 'eq', 'value' => 'expense'],
                ],
            ],
            'category',
            Carbon::today()->startOfDay(),
            Carbon::today()->endOfDay(),
        );

        $this->assertNotEmpty($groups);
        $this->assertArrayHasKey('label', $groups[0]);
        $this->assertArrayHasKey('value', $groups[0]);
    }
}
