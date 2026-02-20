<?php

namespace Tests\Unit;

use App\Models\Currency;
use App\Models\DebtCredit;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase as BaseTestCase;

/**
 * Test per le funzionalità del modello DebtCredit, inclusi calcoli interessi
 * e gestione saldo.
 */
class DebtCreditTest extends BaseTestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->user->active_household_id = $this->household->id;
        $this->user->save();
        
        $this->currency = Currency::firstOrCreate(
            ['code' => 'EUR'],
            ['name' => 'Euro', 'symbol' => '€']
        );
    }

    #[Test]
    public function it_calculates_remaining_amount_correctly()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'initial_amount' => 1000.00,
            'paid_amount' => 250.00,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        $this->assertEquals(750.00, $debt->getRemainingAmount());
    }

    #[Test]
    public function it_uses_amount_as_fallback_when_initial_amount_is_null()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'initial_amount' => null,
            'paid_amount' => 250.00,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        $this->assertEquals(750.00, $debt->getRemainingAmount());
    }

    #[Test]
    public function it_returns_zero_interest_when_no_interest_rate_set()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'initial_amount' => 1000.00,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
            'interest_rate' => null,
        ]);

        $this->assertEquals(0.0, $debt->calculateAccruedInterest());
    }

    #[Test]
    public function it_calculates_simple_interest_correctly()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'initial_amount' => 1000.00,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
            'interest_rate' => 10.00, // 10% annuo
            'interest_type' => 'simple',
            'interest_calculation_date' => now()->subYear(), // 1 anno fa
        ]);

        // Interesse semplice: 1000 * 0.10 * 1 anno = 100
        $interest = $debt->calculateAccruedInterest();
        $this->assertEqualsWithDelta(100.0, $interest, 1.0); // Delta di 1 per tolleranza
    }

    #[Test]
    public function it_calculates_compound_interest_correctly()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'initial_amount' => 1000.00,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
            'interest_rate' => 10.00, // 10% annuo
            'interest_type' => 'compound',
            'interest_calculation_date' => now()->subYear(), // 1 anno fa
        ]);

        // Interesse composto: 1000 * ((1 + 0.10/365)^365 - 1) ≈ 105.16
        $interest = $debt->calculateAccruedInterest();
        $this->assertGreaterThan(100.0, $interest); // Deve essere maggiore dell'interesse semplice
        $this->assertEqualsWithDelta(105.16, $interest, 2.0); // Delta per tolleranza
    }

    #[Test]
    public function it_calculates_total_amount_with_interest()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'initial_amount' => 1000.00,
            'paid_amount' => 200.00,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
            'interest_rate' => 10.00,
            'interest_type' => 'simple',
            'interest_calculation_date' => now()->subMonths(6), // 6 mesi fa
        ]);

        // Saldo rimanente: 1000 - 200 = 800
        // Interesse semplice: 800 * 0.10 * 0.5 anni = 40
        // Totale: 800 + 40 = 840
        $total = $debt->getTotalAmountWithInterest();
        $this->assertEqualsWithDelta(840.0, $total, 2.0);
    }

    #[Test]
    public function it_records_payment_and_updates_status()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'initial_amount' => 1000.00,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        $debt->recordPayment(500.00);
        
        $this->assertEquals(500.00, $debt->paid_amount);
        $this->assertEquals(500.00, $debt->getRemainingAmount());
        $this->assertEquals('open', $debt->status);

        $debt->recordPayment(500.00);
        
        $this->assertEquals(1000.00, $debt->paid_amount);
        $this->assertEquals(0.00, $debt->getRemainingAmount());
        $this->assertEquals('closed', $debt->status);
    }

    #[Test]
    public function it_marks_as_overdue_when_due_date_passed()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'initial_amount' => 1000.00,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
            'due_date' => now()->subDay(), // Scaduto ieri
        ]);

        $debt->recordPayment(100.00);
        
        $this->assertEquals('overdue', $debt->status);
    }

    #[Test]
    public function it_checks_if_debt_is_overdue()
    {
        $overdueDebt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'overdue',
            'due_date' => now()->subDay(),
        ]);

        $openDebt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Another Bank',
            'amount' => 500.00,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
            'due_date' => now()->addMonth(),
        ]);

        $this->assertTrue($overdueDebt->isOverdue());
        $this->assertFalse($openDebt->isOverdue());
    }

    #[Test]
    public function it_checks_if_debt_is_paid()
    {
        $paidDebt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'initial_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'closed',
        ]);

        $unpaidDebt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Another Bank',
            'amount' => 1000.00,
            'initial_amount' => 1000.00,
            'paid_amount' => 500.00,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        $this->assertTrue($paidDebt->isPaid());
        $this->assertFalse($unpaidDebt->isPaid());
    }

    #[Test]
    public function it_has_relationships_with_transactions()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1000.00,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $debt->transactions());
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $debt->recurringTransactions());
    }
}
