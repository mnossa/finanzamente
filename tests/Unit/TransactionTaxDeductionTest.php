<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Attachment;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionTaxDeductionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $this->user->update(['active_household_id' => $this->household->id]);
        
        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_can_create_transaction_with_tax_deduction_fields()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => -100.00,
            'date' => now(),
            'description' => 'Spesa medica',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);
    }

    #[Test]
    public function it_casts_tax_deduction_fields_correctly()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => -200.00,
            'date' => now(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'veterinarie',
            'tax_year' => 2024,
        ]);

        $this->assertIsBool($transaction->is_tax_deductible);
        $this->assertIsString($transaction->tax_deduction_rate);
        $this->assertEquals('19.00', $transaction->tax_deduction_rate);
    }

    #[Test]
    public function it_calculates_tax_deductible_amount_correctly()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => -1000.00,
            'date' => now(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        // 1000 * 19% = 190.00
        $this->assertEquals(190.00, $transaction->getTaxDeductibleAmount());
    }

    #[Test]
    public function it_calculates_tax_deductible_amount_for_positive_amounts()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => 500.00, // Entrata
            'date' => now(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 26.00,
            'tax_deduction_type' => 'donazioni',
            'tax_year' => 2024,
        ]);

        // abs(500) * 26% = 130.00
        $this->assertEquals(130.00, $transaction->getTaxDeductibleAmount());
    }

    #[Test]
    public function it_returns_zero_when_not_tax_deductible()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => -1000.00,
            'date' => now(),
            'is_tax_deductible' => false,
            'tax_deduction_rate' => null,
            'tax_deduction_type' => null,
            'tax_year' => null,
        ]);

        $this->assertEquals(0.0, $transaction->getTaxDeductibleAmount());
    }

    #[Test]
    public function it_returns_zero_when_rate_is_null()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => -1000.00,
            'date' => now(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => null,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        $this->assertEquals(0.0, $transaction->getTaxDeductibleAmount());
    }

    #[Test]
    public function it_checks_if_deductible_for_specific_year()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => -500.00,
            'date' => now(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'istruzione',
            'tax_year' => 2024,
        ]);

        $this->assertTrue($transaction->isDeductibleForYear(2024));
        $this->assertFalse($transaction->isDeductibleForYear(2023));
        $this->assertFalse($transaction->isDeductibleForYear(2025));
    }

    #[Test]
    public function it_returns_false_when_not_deductible()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => -500.00,
            'date' => now(),
            'is_tax_deductible' => false,
            'tax_year' => 2024,
        ]);

        $this->assertFalse($transaction->isDeductibleForYear(2024));
    }

    #[Test]
    public function it_has_attachments_relationship()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => -300.00,
            'date' => now(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $transaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $transaction->attachments);
        $this->assertCount(1, $transaction->attachments);
        $this->assertEquals($attachment->id, $transaction->attachments->first()->id);
    }

    #[Test]
    public function it_can_have_multiple_attachments()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => -500.00,
            'date' => now(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $transaction->id,
            'file_path' => 'attachments/receipt1.pdf',
            'filename' => 'receipt1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $transaction->id,
            'file_path' => 'attachments/receipt2.jpg',
            'filename' => 'receipt2.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $this->assertCount(2, $transaction->fresh()->attachments);
    }

    #[Test]
    public function it_handles_all_tax_deduction_types()
    {
        $types = ['mediche', 'veterinarie', 'istruzione', 'mutuo', 'ristrutturazione', 'assicurazioni', 'previdenza', 'donazioni', 'altro'];

        foreach ($types as $type) {
            $transaction = Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'currency_code' => 'EUR',
                'amount' => -100.00,
                'date' => now(),
                'is_tax_deductible' => true,
                'tax_deduction_rate' => 19.00,
                'tax_deduction_type' => $type,
                'tax_year' => 2024,
            ]);

            $this->assertEquals($type, $transaction->tax_deduction_type);
        }
    }

    #[Test]
    public function it_calculates_deduction_with_different_rates()
    {
        $rates = [19.00, 26.00, 36.00, 50.00, 100.00];
        $amount = 1000.00;

        foreach ($rates as $rate) {
            $transaction = Transaction::create([
                'user_id' => $this->user->id,
                'account_id' => $this->account->id,
                'currency_code' => 'EUR',
                'amount' => -$amount,
                'date' => now(),
                'is_tax_deductible' => true,
                'tax_deduction_rate' => $rate,
                'tax_deduction_type' => 'ristrutturazione',
                'tax_year' => 2024,
            ]);

            $expected = $amount * ($rate / 100);
            $this->assertEquals($expected, $transaction->getTaxDeductibleAmount());
        }
    }

    #[Test]
    public function it_handles_decimal_amounts_correctly()
    {
        $transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'currency_code' => 'EUR',
            'amount' => -123.45,
            'date' => now(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        // 123.45 * 19% = 23.4555 -> rounded
        $this->assertEquals(23.4555, $transaction->getTaxDeductibleAmount());
    }
}
