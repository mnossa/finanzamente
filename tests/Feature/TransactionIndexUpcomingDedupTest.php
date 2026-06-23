<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvestmentPacService;
use App\Services\UpcomingCashflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionIndexUpcomingDedupTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function future_transaction_appears_only_in_upcoming_section_on_default_index(): void
    {
        Carbon::setTestNow('2026-06-15');

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -10,
            'date' => '2026-06-10',
            'description' => 'Passata',
        ]);

        $future = Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -25,
            'date' => '2026-07-01',
            'description' => 'Futura',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', fn ($id) => $id !== $future->id)
                ->has('upcomingMovements', 1)
                ->where('upcomingMovements.0.id', $future->id)
                ->where('upcomingMovements.0.is_virtual', false)
            );

        Carbon::setTestNow();
    }

    #[Test]
    public function future_transaction_stays_in_main_list_when_date_filters_are_applied(): void
    {
        Carbon::setTestNow('2026-06-15');

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        $future = Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'amount' => -25,
            'date' => '2026-07-01',
            'description' => 'Futura',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.id', $future->id)
                ->where('upcomingMovements', [])
            );

        Carbon::setTestNow();
    }

    #[Test]
    public function upcoming_movements_skip_virtual_recurring_when_real_future_transaction_exists(): void
    {
        Carbon::setTestNow('2026-06-04');

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        $recurring = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'amount' => -50,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-06-05',
            'description' => 'Affitto',
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'recurring_transaction_id' => $recurring->id,
            'amount' => -50,
            'date' => '2026-06-05',
            'description' => 'Affitto',
        ]);

        $movements = app(UpcomingCashflowService::class)->buildUpcomingMovements($user);

        $this->assertCount(1, $movements);
        $this->assertFalse($movements[0]['is_virtual']);
        $this->assertSame($recurring->id, $movements[0]['recurring_transaction_id']);

        Carbon::setTestNow();
    }

    #[Test]
    public function upcoming_movements_skip_virtual_pac_when_real_future_transaction_exists_in_same_month(): void
    {
        Carbon::setTestNow('2026-06-04');

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->update(['active_household_id' => $household->id]);
        $household->users()->attach($user->id, ['role' => 'owner']);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);

        $pac = InvestmentPac::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'investment_asset_id' => $asset->id,
            'account_id' => $account->id,
            'amount' => 100,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-06-05',
            'status' => 'active',
        ]);

        $investment = app(InvestmentPacService::class)->runSinglePac($pac, IlluminateCarbon::parse('2026-06-05'), true);

        $this->assertNotNull($investment);

        $movements = app(UpcomingCashflowService::class)->buildUpcomingMovements($user);

        $this->assertCount(1, $movements);
        $this->assertFalse($movements[0]['is_virtual']);
        $this->assertTrue($movements[0]['is_pac']);

        Carbon::setTestNow();
    }
}
