<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Attachment;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxDeductionExportTest extends TestCase
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

        Storage::fake('private');

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
            'name' => 'Spese Mediche',
        ]);
    }

    #[Test]
    public function user_can_view_tax_deductions_index()
    {
        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali');

        $response->assertStatus(200);
    }

    #[Test]
    public function tax_deductions_index_shows_deductible_transactions()
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'date' => now(),
            'description' => 'Spesa medica',
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => now()->year,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -50.00,
            'date' => now(),
            'description' => 'Spesa normale',
            'currency_code' => 'EUR',
            'is_tax_deductible' => false,
        ]);

        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali?year='.now()->year);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('TaxDeductions/Index')
            ->has('transactions', 1)
            ->where('year', now()->year)
        );
    }

    #[Test]
    public function tax_deductions_filters_by_year()
    {
        Transaction::create([
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

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -150.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'veterinarie',
            'tax_year' => 2023,
        ]);

        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali?year=2024');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('transactions', 1)
            ->where('year', 2024)
        );
    }

    #[Test]
    public function tax_deductions_includes_summary()
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -1000.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => now()->year,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -500.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => now()->year,
        ]);

        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali?year='.now()->year);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('summary')
            ->where('summary.total_transactions', 2)
            ->where('summary.total_amount', fn ($value) => $value == 1500.00)
            ->where('summary.total_deductible', fn ($value) => $value == 285.00) // 1500 * 19%
        );
    }

    #[Test]
    public function tax_deductions_groups_by_type()
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => now()->year,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -200.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'veterinarie',
            'tax_year' => now()->year,
        ]);

        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali?year='.now()->year);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('summary.grouped_by_type.mediche', 1)
            ->has('summary.grouped_by_type.veterinarie', 1)
        );
    }

    #[Test]
    public function user_cannot_see_private_transactions_of_others()
    {
        $otherUser = User::factory()->create();
        $this->household->users()->attach($otherUser->id, ['role' => 'member', 'permissions' => json_encode([])]);

        Transaction::create([
            'user_id' => $otherUser->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_private' => true,
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => now()->year,
        ]);

        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali?year='.now()->year);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('transactions', 0)
        );
    }

    #[Test]
    public function user_can_see_own_private_transactions()
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_private' => true,
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => now()->year,
        ]);

        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali?year='.now()->year);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('transactions', 1)
        );
    }

    #[Test]
    public function user_can_export_tax_deductions_as_pdf()
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'date' => now(),
            'description' => 'Spesa medica',
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali/esporta-pdf?year=2024');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="detrazioni_fiscali_2024.html"');
    }

    #[Test]
    public function pdf_export_requires_year_parameter()
    {
        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali/esporta-pdf');

        $response->assertStatus(302); // Redirect con errore di validazione
    }

    #[Test]
    public function pdf_export_validates_year_range()
    {
        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali/esporta-pdf?year=1999');

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['year']);
    }

    #[Test]
    public function user_can_export_attachments_as_zip()
    {
        Storage::disk('private')->put('attachments/receipt1.pdf', 'receipt content 1');
        Storage::disk('private')->put('attachments/receipt2.pdf', 'receipt content 2');

        $transaction1 = Transaction::create([
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

        $transaction2 = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -200.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'veterinarie',
            'tax_year' => 2024,
        ]);

        Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $transaction1->id,
            'file_path' => 'attachments/receipt1.pdf',
            'filename' => 'receipt1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $transaction2->id,
            'file_path' => 'attachments/receipt2.pdf',
            'filename' => 'receipt2.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali/esporta-allegati?year=2024');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('detrazioni_fiscali_2024', $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function zip_export_requires_year_parameter()
    {
        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali/esporta-allegati');

        $response->assertStatus(302);
    }

    #[Test]
    public function zip_export_validates_year_range()
    {
        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali/esporta-allegati?year=2150');

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['year']);
    }

    #[Test]
    public function tax_deductions_only_show_transactions_from_active_household()
    {
        $otherHousehold = Household::factory()->create();
        $otherAccount = Account::factory()->create(['household_id' => $otherHousehold->id]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => now()->year,
        ]);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $otherAccount->id,
            'category_id' => $this->category->id,
            'amount' => -200.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => now()->year,
        ]);

        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali?year='.now()->year);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('transactions', 1) // Solo quella della household attiva
        );
    }

    #[Test]
    public function guest_cannot_access_tax_deductions()
    {
        $response = $this->get('/detrazioni-fiscali');

        $response->assertStatus(302);
        $response->assertRedirect('/accedi');
    }

    #[Test]
    public function guest_cannot_export_pdf()
    {
        $response = $this->get('/detrazioni-fiscali/esporta-pdf?year=2024');

        $response->assertStatus(302);
        $response->assertRedirect('/accedi');
    }

    #[Test]
    public function guest_cannot_export_attachments()
    {
        $response = $this->get('/detrazioni-fiscali/esporta-allegati?year=2024');

        $response->assertStatus(302);
        $response->assertRedirect('/accedi');
    }

    #[Test]
    public function pdf_export_includes_all_transaction_details()
    {
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -1000.00,
            'date' => now(),
            'description' => 'Visita specialistica',
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);

        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali/esporta-pdf?year=2024');

        $response->assertStatus(200);
        $response->assertSee('Visita specialistica');
        $response->assertSee('1.000,00'); // Formato italiano con separatore migliaia
        $response->assertSee('19');
    }

    #[Test]
    public function available_years_includes_current_year()
    {
        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->has('summary.years')
            ->where('summary.years', fn ($years) => collect($years)->contains(now()->year))
        );
    }

    #[Test]
    public function tax_deductions_uses_tax_year_or_transaction_date()
    {
        // Transazione con tax_year esplicito
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -100.00,
            'date' => '2024-12-31',
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2025, // Anno fiscale diverso dall'anno della data
        ]);

        // Transazione senza tax_year, usa l'anno della data
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => -200.00,
            'date' => '2024-06-15',
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'veterinarie',
            'tax_year' => null,
        ]);

        // Cerco transazioni per il 2025
        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali?year=2025');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('transactions', 1));

        // Cerco transazioni per il 2024
        $response = $this->actingAs($this->user)->get('/detrazioni-fiscali?year=2024');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->has('transactions', 1));
    }
}
