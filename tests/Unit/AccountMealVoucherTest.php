<?php

namespace Tests\Unit;

use App\Models\Account;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AccountMealVoucherTest extends TestCase
{
    #[Test]
    public function ticket_count_uses_floor_of_balance_over_unit_value(): void
    {
        $account = new Account([
            'type' => Account::MEAL_VOUCHER_TYPE,
            'ticket_unit_value' => 8,
        ]);

        $this->assertSame(10, $account->ticketCountFromBalance(80));
        $this->assertSame(10, $account->ticketCountFromBalance(84.99));
        $this->assertSame(0, $account->ticketCountFromBalance(7.99));
        $this->assertSame(0, $account->ticketCountFromBalance(0));
        $this->assertSame(0, $account->ticketCountFromBalance(-16));
    }

    #[Test]
    public function ticket_helpers_return_null_for_non_meal_voucher_accounts(): void
    {
        $account = new Account([
            'type' => 'bank',
            'ticket_unit_value' => 8,
        ]);

        $this->assertNull($account->ticketCountFromBalance(80));
        $this->assertNull($account->ticketsDeltaForAmount(16));
    }

    #[Test]
    public function tickets_delta_is_signed_amount_over_unit(): void
    {
        $account = new Account([
            'type' => Account::MEAL_VOUCHER_TYPE,
            'ticket_unit_value' => 8,
        ]);

        $this->assertSame(2.0, $account->ticketsDeltaForAmount(16));
        $this->assertSame(-2.0, $account->ticketsDeltaForAmount(-16));
        $this->assertSame(1.5, $account->ticketsDeltaForAmount(12));
    }
}
