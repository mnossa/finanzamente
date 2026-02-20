<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DebtCredit;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase as BaseTestCase;

/**
 * Test per verificare il collegamento tra transazioni e debiti/crediti,
 * incluso l'aggiornamento automatico del saldo pagato.
 */
class DebtCreditTransactionLinkTest extends BaseTestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;
    private Category $category;
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

        $this->account = Account::create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Test Account',
            'currency_code' => 'EUR',
            'initial_balance' => 5000.00,
            'current_balance' => 5000.00,
            'active' => true,
            'is_private' => false,
        ]);

        $this->category = Category::create([
            'household_id' => $this->household->id,
            'name' => 'Pagamenti Debito',
            'type' => 'expense',
            'color' => '#FF0000',
        ]);
    }

    #[Test]
    public function it_updates_debt_paid_amount_when_transaction_is_created()
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

        // Crea una transazione associata al debito
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -200.00, // Pagamento (negativo perché è una spesa)
            'currency_code' => 'EUR',
            'date' => now(),
            'description' => 'Pagamento rata debito',
            'debt_credit_id' => $debt->id,
        ]);

        // Ricarica il debito dal database
        $debt->refresh();

        // Verifica che paid_amount sia stato aggiornato
        $this->assertEquals(200.00, $debt->paid_amount);
        $this->assertEquals(800.00, $debt->getRemainingAmount());
        $this->assertEquals('open', $debt->status);
    }

    #[Test]
    public function it_closes_debt_when_fully_paid_via_transaction()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 500.00,
            'initial_amount' => 500.00,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        // Crea una transazione che paga completamente il debito
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -500.00,
            'currency_code' => 'EUR',
            'date' => now(),
            'description' => 'Pagamento completo debito',
            'debt_credit_id' => $debt->id,
        ]);

        $debt->refresh();

        $this->assertEquals(500.00, $debt->paid_amount);
        $this->assertEquals(0.00, $debt->getRemainingAmount());
        $this->assertEquals('closed', $debt->status);
    }

    #[Test]
    public function it_updates_debt_when_transaction_is_deleted()
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

        // Crea due transazioni
        $transaction1 = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -300.00,
            'currency_code' => 'EUR',
            'date' => now(),
            'debt_credit_id' => $debt->id,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -200.00,
            'currency_code' => 'EUR',
            'date' => now(),
            'debt_credit_id' => $debt->id,
        ]);

        $debt->refresh();
        $this->assertEquals(500.00, $debt->paid_amount);

        // Elimina una transazione
        $transaction1->delete();

        $debt->refresh();
        $this->assertEquals(200.00, $debt->paid_amount);
        $this->assertEquals(800.00, $debt->getRemainingAmount());
        $this->assertEquals('open', $debt->status);
    }

    #[Test]
    public function it_marks_debt_as_overdue_when_due_date_passed_and_payment_made()
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

        // Crea una transazione parziale
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -200.00,
            'currency_code' => 'EUR',
            'date' => now(),
            'debt_credit_id' => $debt->id,
        ]);

        $debt->refresh();

        $this->assertEquals('overdue', $debt->status);
        $this->assertEquals(200.00, $debt->paid_amount);
    }

    #[Test]
    public function it_handles_multiple_transactions_for_same_debt()
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

        // Crea più transazioni
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'currency_code' => 'EUR',
            'date' => now()->subDays(3),
            'debt_credit_id' => $debt->id,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -150.00,
            'currency_code' => 'EUR',
            'date' => now()->subDays(2),
            'debt_credit_id' => $debt->id,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -250.00,
            'currency_code' => 'EUR',
            'date' => now()->subDay(),
            'debt_credit_id' => $debt->id,
        ]);

        $debt->refresh();

        $this->assertEquals(500.00, $debt->paid_amount);
        $this->assertEquals(500.00, $debt->getRemainingAmount());
        $this->assertEquals(3, $debt->transactions()->count());
    }

    #[Test]
    public function recurring_transactions_pass_debt_credit_id_to_generated_transactions()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1200.00,
            'initial_amount' => 1200.00,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        // Crea una transazione ricorrente mensile associata al debito
        $recurringTransaction = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00, // Rata mensile
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => now()->subMonths(2),
            'description' => 'Rata mensile debito',
            'debt_credit_id' => $debt->id,
        ]);

        // Genera le transazioni fino ad oggi
        $service = app(RecurringTransactionService::class);
        $count = $service->generateTransactionsUntil($recurringTransaction);

        $this->assertGreaterThan(0, $count);

        // Verifica che le transazioni generate abbiano debt_credit_id
        $generatedTransactions = Transaction::where('recurring_transaction_id', $recurringTransaction->id)->get();
        
        foreach ($generatedTransactions as $transaction) {
            $this->assertEquals($debt->id, $transaction->debt_credit_id);
        }

        // Verifica che il debito sia stato aggiornato
        $debt->refresh();
        $this->assertGreaterThan(0, $debt->paid_amount);
    }

    #[Test]
    public function transaction_can_check_if_it_is_debt_payment()
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

        $debtTransaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -200.00,
            'currency_code' => 'EUR',
            'date' => now(),
            'debt_credit_id' => $debt->id,
        ]);

        $normalTransaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50.00,
            'currency_code' => 'EUR',
            'date' => now(),
        ]);

        $this->assertTrue($debtTransaction->isDebtPayment());
        $this->assertFalse($normalTransaction->isDebtPayment());
    }

    #[Test]
    public function recurring_transaction_can_check_if_it_is_debt_payment()
    {
        $debt = DebtCredit::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'counterparty' => 'Test Bank',
            'amount' => 1200.00,
            'currency_code' => 'EUR',
            'type' => 'debt',
            'status' => 'open',
        ]);

        $debtRecurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => now(),
            'debt_credit_id' => $debt->id,
        ]);

        $normalRecurring = RecurringTransaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50.00,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => now(),
        ]);

        $this->assertTrue($debtRecurring->isDebtPayment());
        $this->assertFalse($normalRecurring->isDebtPayment());
    }
}
