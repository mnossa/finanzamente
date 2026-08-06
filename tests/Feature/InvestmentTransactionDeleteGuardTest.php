<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvestmentTransactionSyncService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentTransactionDeleteGuardTest extends TestCase
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
        ]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => 'EUR',
            'initial_balance' => 5000,
        ]);
    }

    #[Test]
    public function cannot_delete_investment_linked_transaction(): void
    {
        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);

        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $asset->id,
            'quantity' => 1,
            'buy_price' => 100,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);
        $transaction = Transaction::where('investment_id', $investment->id)->firstOrFail();

        $this->actingAs($this->user)
            ->delete(route('transactions.destroy', $transaction))
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'deleted_at' => null]);
    }

    #[Test]
    public function bulk_delete_aborts_when_selection_includes_investment_transaction(): void
    {
        $asset = InvestmentAsset::create([
            'type' => 'etf',
            'symbol' => 'SWDA',
            'name' => 'iShares Core MSCI World',
            'currency_code' => 'EUR',
        ]);

        $investment = Investment::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'account_id' => $this->account->id,
            'asset_id' => $asset->id,
            'quantity' => 1,
            'buy_price' => 100,
            'buy_date' => now()->toDateString(),
            'is_private' => false,
        ]);

        app(InvestmentTransactionSyncService::class)->syncPurchase($investment);
        $linked = Transaction::where('investment_id', $investment->id)->firstOrFail();

        $manual = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'amount' => -25,
            'currency_code' => 'EUR',
            'date' => now(),
        ]);

        $this->actingAs($this->user)
            ->delete(route('transactions.bulk-destroy'), ['ids' => [$linked->id, $manual->id]])
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('transactions', ['id' => $linked->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('transactions', ['id' => $manual->id, 'deleted_at' => null]);
    }
}
