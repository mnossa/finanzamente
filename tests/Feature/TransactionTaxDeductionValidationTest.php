<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionTaxDeductionValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
        ]);

        $this->category = Category::factory()->create([
            'household_id' => $this->household->id,
        ]);
    }

    #[Test]
    public function transaction_can_be_created_without_tax_deduction_fields()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'description' => 'Test transaction',
        ]);

        $response->assertStatus(302); // Redirect after success
        $this->assertDatabaseHas('transactions', [
            'amount' => -100.00,
            'is_tax_deductible' => false,
        ]);
    }

    #[Test]
    public function transaction_can_be_created_with_tax_deduction_fields()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'description' => 'Spesa medica',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('transactions', [
            'description' => 'Spesa medica',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);
    }

    #[Test]
    public function tax_deduction_rate_is_required_when_is_tax_deductible_is_true()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_type' => 'mediche',
            // tax_deduction_rate mancante
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tax_deduction_rate']);
    }

    #[Test]
    public function tax_deduction_type_is_required_when_is_tax_deductible_is_true()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            // tax_deduction_type mancante
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tax_deduction_type']);
    }

    #[Test]
    public function tax_deduction_rate_must_be_numeric()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 'not-a-number',
            'tax_deduction_type' => 'mediche',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tax_deduction_rate']);
    }

    #[Test]
    public function tax_deduction_rate_must_be_at_least_0_01()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 0.00,
            'tax_deduction_type' => 'mediche',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tax_deduction_rate']);
    }

    #[Test]
    public function tax_deduction_rate_cannot_exceed_100()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 100.01,
            'tax_deduction_type' => 'mediche',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tax_deduction_rate']);
    }

    #[Test]
    public function tax_deduction_type_must_be_valid()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tax_deduction_type']);
    }

    #[Test]
    public function tax_deduction_type_accepts_all_valid_types()
    {
        $validTypes = ['mediche', 'veterinarie', 'istruzione', 'mutuo', 'ristrutturazione', 'assicurazioni', 'previdenza', 'donazioni', 'altro'];

        foreach ($validTypes as $type) {
            $response = $this->actingAs($this->user)->postJson('/transactions', [
                'account_id' => $this->account->id,
                'category_id' => $this->category->id,
                'amount' => 100.00,
                'date' => now()->toDateString(),
                'description' => "Test $type",
                'is_tax_deductible' => true,
                'tax_deduction_rate' => 19.00,
                'tax_deduction_type' => $type,
                'tax_year' => 2024,
            ]);

            $response->assertStatus(302); // Success
            $this->assertDatabaseHas('transactions', [
                'tax_deduction_type' => $type,
            ]);
        }
    }

    #[Test]
    public function tax_year_is_optional()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            // tax_year non specificato
        ]);

        $response->assertStatus(302);
        // Quando tax_year non è specificato ma is_tax_deductible è true,
        // il controller imposta automaticamente tax_year all'anno della data
        $this->assertDatabaseHas('transactions', [
            'is_tax_deductible' => true,
            'tax_year' => now()->year,
        ]);
    }

    #[Test]
    public function tax_year_must_be_integer()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 'not-a-year',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tax_year']);
    }

    #[Test]
    public function tax_year_must_be_at_least_2000()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 1999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tax_year']);
    }

    #[Test]
    public function tax_year_cannot_exceed_2100()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2101,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tax_year']);
    }

    #[Test]
    public function tax_deduction_rate_allows_decimals()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.50,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('transactions', [
            'tax_deduction_rate' => 19.50,
        ]);
    }

    #[Test]
    public function validation_errors_have_italian_messages()
    {
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            // Mancano rate e type
        ]);

        $response->assertStatus(422);
        $json = $response->json();
        
        // Verifica che i messaggi siano in italiano
        $this->assertStringContainsString('detrazione', strtolower($json['errors']['tax_deduction_rate'][0]));
        $this->assertStringContainsString('detrazione', strtolower($json['errors']['tax_deduction_type'][0]));
    }

    #[Test]
    public function tax_deduction_fields_can_be_updated()
    {
        $transaction = \App\Models\Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => false,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/transactions/{$transaction->id}", [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);
    }

    #[Test]
    public function tax_deduction_fields_can_be_removed_on_update()
    {
        $transaction = \App\Models\Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/transactions/{$transaction->id}", [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => false,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'is_tax_deductible' => false,
        ]);
    }

    #[Test]
    public function boundary_values_for_tax_deduction_rate_are_accepted()
    {
        // Test min valore valido
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 0.01,
            'tax_deduction_type' => 'mediche',
        ]);

        $response->assertStatus(302);

        // Test max valore valido
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 100.00,
            'tax_deduction_type' => 'mediche',
        ]);

        $response->assertStatus(302);
    }

    #[Test]
    public function boundary_values_for_tax_year_are_accepted()
    {
        // Test min valore valido
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2000,
        ]);

        $response->assertStatus(302);

        // Test max valore valido
        $response = $this->actingAs($this->user)->postJson('/transactions', [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2100,
        ]);

        $response->assertStatus(302);
    }
}
