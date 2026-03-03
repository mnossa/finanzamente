<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LifestyleScoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;
    private Category $incomeCategory;
    private Category $expenseCategory;
    private Category $investmentCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create([
            'user_type' => 'persona',
        ]);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id'  => $this->household->id,
            'owner_user_id' => $this->user->id,
        ]);

        $this->incomeCategory = Category::factory()->create([
            'household_id' => $this->household->id,
            'type'         => 'income',
            'name'         => 'Stipendio',
        ]);

        $this->expenseCategory = Category::factory()->create([
            'household_id'                 => $this->household->id,
            'type'                         => 'expense',
            'name'                         => 'Generiche',
            'exclude_from_lifestyle_score' => false,
        ]);

        $this->investmentCategory = Category::factory()->create([
            'household_id'                 => $this->household->id,
            'type'                         => 'expense',
            'name'                         => 'Investimenti',
            'exclude_from_lifestyle_score' => true,
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_lifestyle_score(): void
    {
        $response = $this->get('/lifestyle-score');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_user_can_view_lifestyle_score_page(): void
    {
        $response = $this->actingAs($this->user)->get('/lifestyle-score');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('LifestyleScore/Index'));
    }

    #[Test]
    public function lifestyle_score_page_shows_trend_data(): void
    {
        $response = $this->actingAs($this->user)->get('/lifestyle-score');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('LifestyleScore/Index')
            ->has('trend')
            ->has('trend.direction')
            ->has('trend.delta')
            ->has('trend.last30_score')
            ->has('trend.prev30_score')
            ->has('dateRangeLabel')
        );
    }

    #[Test]
    public function lifestyle_score_is_null_with_no_income(): void
    {
        // Solo spese, nessun reddito → score deve essere null
        Transaction::create([
            'user_id'     => $this->user->id,
            'account_id'  => $this->account->id,
            'category_id' => $this->expenseCategory->id,
            'amount'      => -500.00,
            'date'        => now()->startOfMonth(),
            'description' => 'Spesa test',
            'currency_code' => 'EUR',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/lifestyle-score');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('LifestyleScore/Index')
            ->where('metrics.lifestyle_score', null)
        );
    }

    #[Test]
    public function lifestyle_score_is_calculated_correctly_for_persona(): void
    {
        // Reddito 2000 €, spese 1000 €, investimenti 500 € (esclusi)
        // SpeseEffettive = 1000 - 500 = 500
        // Score = (2000 - 500) / 2000 * 100 = 75%
        Transaction::create([
            'user_id'       => $this->user->id,
            'account_id'    => $this->account->id,
            'category_id'   => $this->incomeCategory->id,
            'amount'        => 2000.00,
            'date'          => now()->startOfMonth(),
            'description'   => 'Stipendio',
            'currency_code' => 'EUR',
        ]);

        Transaction::create([
            'user_id'       => $this->user->id,
            'account_id'    => $this->account->id,
            'category_id'   => $this->expenseCategory->id,
            'amount'        => -500.00,
            'date'          => now()->startOfMonth(),
            'description'   => 'Spese varie',
            'currency_code' => 'EUR',
        ]);

        // Transazione in categoria investimenti (esclusa)
        Transaction::create([
            'user_id'       => $this->user->id,
            'account_id'    => $this->account->id,
            'category_id'   => $this->investmentCategory->id,
            'amount'        => -500.00,
            'date'          => now()->startOfMonth(),
            'description'   => 'ETF',
            'currency_code' => 'EUR',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/lifestyle-score');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('LifestyleScore/Index')
            ->where('metrics.gross_income', 2000)
            ->where('metrics.net_income', 2000)         // persona: no tasse
            ->where('metrics.total_expenses', 1000)
            ->where('metrics.excluded_expenses', 500)
            ->where('metrics.effective_expenses', 500)
            ->where('metrics.lifestyle_score', 75)
        );
    }

    #[Test]
    public function lifestyle_score_deducts_taxes_for_partita_iva(): void
    {
        $this->user->update([
            'user_type'       => 'partita_iva',
            'profile_settings' => [
                'has_vat'   => true,
                'tax_rate'  => 15,
                'inps_rate' => 0,  // semplificazione per il test
            ],
        ]);

        // Reddito 1000 €, 15% tasse → netto 850 €
        // Spese 500 €, score = (850 - 500) / 850 * 100 ≈ 41.2%
        Transaction::create([
            'user_id'       => $this->user->id,
            'account_id'    => $this->account->id,
            'category_id'   => $this->incomeCategory->id,
            'amount'        => 1000.00,
            'date'          => now()->startOfMonth(),
            'description'   => 'Fattura',
            'currency_code' => 'EUR',
        ]);

        Transaction::create([
            'user_id'       => $this->user->id,
            'account_id'    => $this->account->id,
            'category_id'   => $this->expenseCategory->id,
            'amount'        => -500.00,
            'date'          => now()->startOfMonth(),
            'description'   => 'Spese',
            'currency_code' => 'EUR',
        ]);

        $response = $this->actingAs($this->user)
            ->get('/lifestyle-score');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('LifestyleScore/Index')
            ->where('metrics.gross_income', 1000)
            ->where('metrics.estimated_taxes', 150)
            ->where('metrics.net_income', 850)
        );
    }

    #[Test]
    public function transfers_are_excluded_from_score_calculation(): void
    {
        Transaction::create([
            'user_id'       => $this->user->id,
            'account_id'    => $this->account->id,
            'category_id'   => $this->incomeCategory->id,
            'amount'        => 1000.00,
            'date'          => now()->startOfMonth(),
            'description'   => 'Stipendio',
            'currency_code' => 'EUR',
        ]);

        // Crea un secondo conto e un trasferimento reale
        $secondAccount = Account::factory()->create([
            'household_id'  => $this->household->id,
            'owner_user_id' => $this->user->id,
        ]);

        // Inserisci un transfer record valido
        $transferId = DB::table('transfers')->insertGetId([
            'uuid'                   => \Illuminate\Support\Str::uuid()->toString(),
            'source_account_id'      => $this->account->id,
            'destination_account_id' => $secondAccount->id,
            'source_amount'          => 300,
            'source_currency'        => 'EUR',
            'dest_amount'            => 300,
            'dest_currency'          => 'EUR',
            'user_id'                => $this->user->id,
            'status'                 => 'completed',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        Transaction::create([
            'user_id'        => $this->user->id,
            'account_id'     => $this->account->id,
            'category_id'    => null,
            'amount'         => -300.00,
            'date'           => now()->startOfMonth(),
            'description'    => 'Trasferimento conto corrente',
            'currency_code'  => 'EUR',
            'transfer_id'    => $transferId,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/lifestyle-score');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('LifestyleScore/Index')
            ->where('metrics.gross_income', 1000)
            ->where('metrics.total_expenses', 0)    // il trasferimento non è una spesa
        );
    }

    #[Test]
    public function xls_export_returns_xlsx_file(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/lifestyle-score/export-xls');

        $response->assertStatus(200);
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    #[Test]
    public function pdf_export_returns_html_file(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/lifestyle-score/export-pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Lifestyle Inflation Score', $response->getContent());
    }
}
