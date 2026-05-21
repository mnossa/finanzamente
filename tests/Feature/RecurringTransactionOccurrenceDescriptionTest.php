<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringTransactionOccurrenceDescriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function generated_transaction_includes_month_and_year_in_description(): void
    {
        Carbon::setTestNow('2026-03-15');

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create(['household_id' => $household->id, 'owner_user_id' => $user->id]);
        $category = Category::factory()->create(['household_id' => $household->id, 'type' => 'expense']);

        $recurring = RecurringTransaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -15,
            'currency_code' => 'EUR',
            'frequency' => 'monthly',
            'start_date' => '2026-03-01',
            'description' => 'Abbonamento',
        ]);

        app(RecurringTransactionService::class)->generateTransactionsUntil($recurring);

        $this->assertDatabaseHas('transactions', [
            'recurring_transaction_id' => $recurring->id,
            'description' => 'Abbonamento - Marzo 2026',
        ]);

        Carbon::setTestNow();
    }
}
