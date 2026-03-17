<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankImportLayout;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvestmentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;
    private InvestmentAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user      = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role'        => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id'   => $this->household->id,
            'owner_user_id'  => $this->user->id,
            'current_balance'=> 10000.00,
            'currency_code'  => 'EUR',
        ]);

        $this->asset = InvestmentAsset::create([
            'type'          => 'stock',
            'symbol'        => 'AAPL',
            'isin'          => 'US0378331005',
            'name'          => 'Apple Inc.',
            'currency_code' => 'USD',
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_investment_import(): void
    {
        $this->withoutVite();
        $response = $this->get('/investments/import');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_user_can_view_import_wizard(): void
    {
        $this->withoutVite();
        $response = $this->actingAs($this->user)->get('/investments/import');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Investments/Import')
            ->has('accounts')
            ->has('userLayouts')
            ->has('assets')
        );
    }

    #[Test]
    public function user_can_preview_investment_csv(): void
    {
        $csvContent = "Data;Ticker;Quantità;Prezzo\n01/01/2024;AAPL;10;180,50\n";
        $file = UploadedFile::fake()->createWithContent('investments.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/investments/import/preview', [
            'csv_file'     => $file,
            'delimiter'    => ';',
            'date_format'  => 'd/m/Y',
            'has_header'   => true,
            'encoding'     => 'UTF-8',
            'column_mapping' => [
                'buy_date'  => 0,
                'ticker'    => 1,
                'quantity'  => 2,
                'buy_price' => 3,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'headers', 'valid', 'invalid', 'total', 'valid_count', 'invalid_count', 'missing_asset_count',
        ]);
        $response->assertJson([
            'total'       => 1,
            'valid_count' => 1,
        ]);
    }

    #[Test]
    public function preview_resolves_known_ticker_to_asset(): void
    {
        $csvContent = "Data;Ticker;Quantità;Prezzo\n01/01/2024;AAPL;10;180,50\n";
        $file = UploadedFile::fake()->createWithContent('investments.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/investments/import/preview', [
            'csv_file'     => $file,
            'delimiter'    => ';',
            'date_format'  => 'd/m/Y',
            'has_header'   => true,
            'encoding'     => 'UTF-8',
            'column_mapping' => [
                'buy_date'  => 0,
                'ticker'    => 1,
                'quantity'  => 2,
                'buy_price' => 3,
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['valid']);
        $this->assertFalse($data['valid'][0]['asset_missing']);
        $this->assertEquals($this->asset->id, $data['valid'][0]['asset_id']);
    }

    #[Test]
    public function preview_marks_unknown_ticker_as_asset_missing(): void
    {
        $csvContent = "Data;Ticker;Quantità;Prezzo\n01/01/2024;UNKNOWN;5;50,00\n";
        $file = UploadedFile::fake()->createWithContent('investments.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/investments/import/preview', [
            'csv_file'     => $file,
            'delimiter'    => ';',
            'date_format'  => 'd/m/Y',
            'has_header'   => true,
            'encoding'     => 'UTF-8',
            'column_mapping' => [
                'buy_date'  => 0,
                'ticker'    => 1,
                'quantity'  => 2,
                'buy_price' => 3,
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['valid']);
        $this->assertTrue($data['valid'][0]['asset_missing']);
        $this->assertEquals(1, $data['missing_asset_count']);
    }

    #[Test]
    public function preview_resolves_isin_to_asset(): void
    {
        $csvContent = "Data;ISIN;Quantità;Prezzo\n15/03/2024;US0378331005;2;190,00\n";
        $file = UploadedFile::fake()->createWithContent('investments.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/investments/import/preview', [
            'csv_file'     => $file,
            'delimiter'    => ';',
            'date_format'  => 'd/m/Y',
            'has_header'   => true,
            'encoding'     => 'UTF-8',
            'column_mapping' => [
                'buy_date'  => 0,
                'isin'      => 1,
                'quantity'  => 2,
                'buy_price' => 3,
            ],
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertFalse($data['valid'][0]['asset_missing']);
        $this->assertEquals($this->asset->id, $data['valid'][0]['asset_id']);
    }

    #[Test]
    public function preview_rejects_invalid_file_format(): void
    {
        $file = UploadedFile::fake()->create('investments.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)->postJson('/investments/import/preview', [
            'csv_file'     => $file,
            'delimiter'    => ';',
            'date_format'  => 'd/m/Y',
            'has_header'   => true,
            'encoding'     => 'UTF-8',
            'column_mapping' => ['buy_date' => 0, 'quantity' => 1, 'buy_price' => 2],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function user_can_import_investments(): void
    {
        $response = $this->actingAs($this->user)->postJson('/investments/import', [
            'rows' => [
                [
                    'buy_date'  => '2024-01-01',
                    'quantity'  => 10,
                    'buy_price' => 180.50,
                    'asset_id'  => $this->asset->id,
                    'fees'      => 5.00,
                    'notes'     => 'Test import',
                ],
            ],
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseCount('investments', 1);
        $this->assertDatabaseHas('investments', [
            'user_id'   => $this->user->id,
            'asset_id'  => $this->asset->id,
            'quantity'  => 10,
            'buy_price' => 180.50,
        ]);
    }

    #[Test]
    public function import_is_atomic_on_invalid_row(): void
    {
        $response = $this->actingAs($this->user)->postJson('/investments/import', [
            'rows' => [
                [
                    'buy_date'  => '2024-01-01',
                    'quantity'  => 5,
                    'buy_price' => 100.00,
                    'asset_id'  => 9999, // Non-existent asset
                ],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('investments', 0);
    }

    #[Test]
    public function import_with_cash_transaction_creates_transaction(): void
    {
        $initialBalance = $this->account->current_balance;

        $response = $this->actingAs($this->user)->postJson('/investments/import', [
            'account_id'              => $this->account->id,
            'create_cash_transaction' => true,
            'rows' => [
                [
                    'buy_date'  => '2024-01-01',
                    'quantity'  => 10,
                    'buy_price' => 100.00,
                    'asset_id'  => $this->asset->id,
                    'fees'      => 5.00,
                ],
            ],
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseCount('investments', 1);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $this->account->id,
            'amount'     => -1005.00, // 10 * 100 + 5 fees
        ]);

        // Verifica che il saldo sia stato aggiornato
        $this->account->refresh();
        $this->assertEquals($initialBalance - 1005.00, $this->account->current_balance);
    }

    #[Test]
    public function import_without_cash_transaction_does_not_create_transaction(): void
    {
        $this->actingAs($this->user)->postJson('/investments/import', [
            'account_id'              => $this->account->id,
            'create_cash_transaction' => false,
            'rows' => [
                [
                    'buy_date'  => '2024-01-01',
                    'quantity'  => 10,
                    'buy_price' => 100.00,
                    'asset_id'  => $this->asset->id,
                ],
            ],
        ]);

        $this->assertDatabaseCount('investments', 1);
        $this->assertDatabaseCount('transactions', 0);
    }

    #[Test]
    public function import_requires_at_least_one_row(): void
    {
        $response = $this->actingAs($this->user)->postJson('/investments/import', [
            'rows' => [],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function user_can_save_investment_import_layout(): void
    {
        $response = $this->actingAs($this->user)->postJson('/investments/import/layouts', [
            'name'           => 'Il mio broker',
            'bank_name'      => 'custom',
            'delimiter'      => ';',
            'date_format'    => 'd/m/Y',
            'has_header'     => true,
            'encoding'       => 'UTF-8',
            'column_mapping' => [
                'buy_date'  => 0,
                'quantity'  => 1,
                'buy_price' => 2,
                'ticker'    => 3,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('bank_import_layouts', [
            'user_id'    => $this->user->id,
            'name'       => 'Il mio broker',
            'model_type' => 'investment',
        ]);
    }

    #[Test]
    public function user_cannot_delete_another_users_layout(): void
    {
        $otherUser = User::factory()->create();
        $layout    = BankImportLayout::factory()->create([
            'user_id'    => $otherUser->id,
            'model_type' => 'investment',
        ]);

        $response = $this->actingAs($this->user)->delete("/investments/import/layouts/{$layout->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function investment_import_layouts_are_isolated_from_transaction_layouts(): void
    {
        // Crea un layout transazione
        BankImportLayout::factory()->create([
            'user_id'    => $this->user->id,
            'model_type' => 'transaction',
            'name'       => 'Layout Transazioni',
        ]);

        // Crea un layout investimento
        BankImportLayout::factory()->create([
            'user_id'    => $this->user->id,
            'model_type' => 'investment',
            'name'       => 'Layout Investimenti',
        ]);

        $this->withoutVite();
        $response = $this->actingAs($this->user)->get('/investments/import');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Investments/Import')
            ->where('userLayouts.0.name', 'Layout Investimenti')
            ->count('userLayouts', 1)
        );
    }

    #[Test]
    public function sheets_endpoint_rejects_non_xlsx_file(): void
    {
        $csvContent = "Data;Ticker;Quantità;Prezzo\n01/01/2024;AAPL;10;180,50\n";
        $file = UploadedFile::fake()->createWithContent('investments.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/investments/import/sheets', [
            'csv_file' => $file,
        ]);

        // Il validator rifiuta file non-XLSX con 422
        $response->assertStatus(422);
    }

    #[Test]
    public function sheets_endpoint_requires_file_or_drive_params(): void
    {
        $response = $this->actingAs($this->user)->postJson('/investments/import/sheets', []);

        $response->assertStatus(422);
    }

    #[Test]
    public function investment_preview_with_sheet_index_is_accepted(): void
    {
        $csvContent = "Data;Ticker;Quantità;Prezzo\n01/01/2024;AAPL;10;180,50\n";
        $file = UploadedFile::fake()->createWithContent('investments.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/investments/import/preview', [
            'csv_file'     => $file,
            'delimiter'    => ';',
            'date_format'  => 'd/m/Y',
            'has_header'   => true,
            'encoding'     => 'UTF-8',
            'sheet_index'  => 0,
            'column_mapping' => [
                'buy_date'  => 0,
                'ticker'    => 1,
                'quantity'  => 2,
                'buy_price' => 3,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['total' => 1, 'valid_count' => 1]);
    }

    #[Test]
    public function investment_preview_with_google_drive_missing_token_returns_validation_error(): void
    {
        $response = $this->actingAs($this->user)->postJson('/investments/import/preview', [
            'google_drive_file_id' => 'some-file-id',
            // google_drive_access_token mancante
            'column_mapping' => ['buy_date' => 0, 'quantity' => 1, 'buy_price' => 2, 'ticker' => 3],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['google_drive_access_token']);
    }

    #[Test]
    public function investment_preview_with_google_drive_returns_error_on_failed_download(): void
    {
        Http::fake([
            'https://www.googleapis.com/*' => Http::response('Unauthorized', 401),
        ]);

        $response = $this->actingAs($this->user)->postJson('/investments/import/preview', [
            'google_drive_file_id'      => 'fake-file-id',
            'google_drive_access_token' => 'fake-token',
            'google_drive_mime_type'    => 'text/csv',
            'column_mapping'            => ['buy_date' => 0, 'quantity' => 1, 'buy_price' => 2, 'ticker' => 3],
        ]);

        $response->assertStatus(422);
    }
}
