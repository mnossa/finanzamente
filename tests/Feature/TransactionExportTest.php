<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_returns_csv_with_filtered_transactions(): void
    {
        $user = $this->createUserWithHousehold();
        $account = Account::where('household_id', $user->active_household_id)->first();
        $categoryA = Category::factory()->create(['household_id' => $user->active_household_id, 'type' => 'expense']);
        $categoryB = Category::factory()->create(['household_id' => $user->active_household_id, 'type' => 'expense']);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $categoryA->id,
            'amount' => -10,
        ]);
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $categoryB->id,
            'amount' => -20,
        ]);

        $response = $this->actingAs($user)
            ->get(route('transactions.export', ['category_id' => $categoryA->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('transazioni-', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
        $this->assertStringContainsString('Descrizione', $response->streamedContent());
    }

    private function createUserWithHousehold(): User
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'Test',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);

        $user->update(['active_household_id' => $household->id]);

        Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        return $user;
    }
}
