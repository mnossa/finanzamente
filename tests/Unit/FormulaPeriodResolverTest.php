<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FormulaPeriodResolver;
use App\Services\FormulaResolverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaPeriodResolverTest extends TestCase
{
    use RefreshDatabase;

    private FormulaPeriodResolver $periodResolver;

    private FormulaResolverService $formulaResolver;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 12, 0, 0));

        $this->periodResolver = app(FormulaPeriodResolver::class);
        $this->formulaResolver = app(FormulaResolverService::class);

        $this->user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $this->user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $this->user->id,
            'initial_balance' => 0,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => 1000,
            'date' => '2021-03-10',
            'description' => 'Storico',
            'currency_code' => 'EUR',
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'amount' => 500,
            'date' => '2026-06-01',
            'description' => 'Recente',
            'currency_code' => 'EUR',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function month_buckets_keep_most_recent_months_when_history_exceeds_limit(): void
    {
        $period = $this->periodResolver->resolve('full_history', $this->user);

        $buckets = $this->periodResolver->monthBuckets($period['start'], $period['end']);

        $this->assertCount(24, $buckets);
        $this->assertTrue($period['start']->lt(Carbon::create(2024, 1, 1)), 'Lo storico utente parte prima del 2024');
        $this->assertTrue($buckets[0]['start']->gte(Carbon::create(2024, 7, 1)->startOfDay()));
        $this->assertTrue($buckets[array_key_last($buckets)]['end']->isSameMonth(Carbon::now()));
    }

    #[Test]
    public function patrimonio_total_monthly_series_reaches_current_month_on_long_history(): void
    {
        $period = $this->periodResolver->resolve('full_history', $this->user);

        $series = $this->formulaResolver->evaluateMonthlySeries(
            $this->user,
            'patrimonio_total',
            $period['start'],
            $period['end'],
        );

        $this->assertNotEmpty($series);
        $this->assertSame(
            Carbon::now()->translatedFormat('M Y'),
            $series[array_key_last($series)]['label'],
        );
        $this->assertGreaterThan(1000.0, $series[array_key_last($series)]['value']);
    }
}
