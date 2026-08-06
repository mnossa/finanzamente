<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FormulaWidgetDataVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaWidgetDataVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private FormulaWidgetDataVersionService $service;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FormulaWidgetDataVersionService::class);

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function version_changes_when_transaction_is_added(): void
    {
        $user = $this->user;
        $account = Account::factory()->for($this->household)->create();

        $before = $this->service->resolveForUser($user);

        Transaction::factory()->for($account)->create();

        $after = $this->service->resolveForUser($user->fresh());

        $this->assertNotSame($before, $after);
    }

    #[Test]
    public function version_changes_when_transaction_is_removed(): void
    {
        $user = $this->user;
        $account = Account::factory()->for($this->household)->create();
        $transaction = Transaction::factory()->for($account)->create();

        $before = $this->service->resolveForUser($user);

        $transaction->delete();

        $after = $this->service->resolveForUser($user->fresh());

        $this->assertNotSame($before, $after);
    }

    #[Test]
    public function version_changes_when_transaction_date_changes(): void
    {
        $user = $this->user;
        $account = Account::factory()->for($this->household)->create();
        $transaction = Transaction::factory()->for($account)->create([
            'date' => '2026-05-01',
        ]);

        $before = $this->service->resolveForUser($user);

        $transaction->update(['date' => '2026-06-15']);

        $after = $this->service->resolveForUser($user->fresh());

        $this->assertNotSame($before, $after);
    }

    #[Test]
    public function version_changes_when_formula_widget_is_added(): void
    {
        $user = $this->user;
        $variable = FinancialVariable::factory()->for($user)->formula('[household_balance]')->create();

        $before = $this->service->resolveForUser($user);

        FormulaWidget::factory()
            ->for($user)
            ->for($variable, 'financialVariable')
            ->create();

        $after = $this->service->resolveForUser($user->fresh());

        $this->assertNotSame($before, $after);
    }
}
