<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BankImportLayout;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;

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
    }

    #[Test]
    public function unauthenticated_user_cannot_access_import_wizard(): void
    {
        $response = $this->get('/transactions/import');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function user_can_preview_csv(): void
    {
        $csvContent = "Data;Descrizione;Importo\n01/01/2024;Supermercato;-50,00\n05/01/2024;Stipendio;1500,00\n";
        $file = UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/transactions/import/preview', [
            'csv_file' => $file,
            'bank_name' => 'custom',
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'date' => 0,
                'description' => 1,
                'amount' => 2,
                'notes' => null,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['headers', 'valid', 'invalid', 'total', 'valid_count', 'invalid_count']);
        $response->assertJson(['total' => 2, 'valid_count' => 2, 'invalid_count' => 0]);
    }

    #[Test]
    public function preview_rejects_invalid_csv_format(): void
    {
        $file = UploadedFile::fake()->create('transactions.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)->postJson('/transactions/import/preview', [
            'csv_file' => $file,
            'bank_name' => 'custom',
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => ['date' => 0, 'description' => 1, 'amount' => 2],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function user_can_import_transactions(): void
    {
        $response = $this->actingAs($this->user)->postJson('/transactions/import', [
            'account_id' => $this->account->id,
            'rows' => [
                ['date' => '2024-01-01', 'amount' => -50.00, 'description' => 'Supermercato', 'notes' => null],
                ['date' => '2024-01-05', 'amount' => 1500.00, 'description' => 'Stipendio', 'notes' => null],
            ],
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', ['description' => 'Supermercato', 'amount' => -50.00]);
        $this->assertDatabaseHas('transactions', ['description' => 'Stipendio', 'amount' => 1500.00]);
    }

    #[Test]
    public function import_requires_valid_account(): void
    {
        $response = $this->actingAs($this->user)->postJson('/transactions/import', [
            'account_id' => 9999,
            'rows' => [
                ['date' => '2024-01-01', 'amount' => -50.00, 'description' => 'Test'],
            ],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function import_requires_at_least_one_row(): void
    {
        $response = $this->actingAs($this->user)->postJson('/transactions/import', [
            'account_id' => $this->account->id,
            'rows' => [],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function user_can_save_custom_layout(): void
    {
        $response = $this->actingAs($this->user)->postJson('/bank-import-layouts', [
            'name' => 'Il mio layout',
            'bank_name' => 'custom',
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => ['date' => 0, 'description' => 1, 'amount' => 2, 'notes' => null],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bank_import_layouts', [
            'user_id' => $this->user->id,
            'name' => 'Il mio layout',
            'bank_name' => 'custom',
        ]);
    }

    #[Test]
    public function user_cannot_delete_another_users_layout(): void
    {
        $otherUser = User::factory()->create();
        $layout = BankImportLayout::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)->delete("/bank-import-layouts/{$layout->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function sheets_endpoint_rejects_non_xlsx_file(): void
    {
        $csvContent = "Data;Descrizione;Importo\n01/01/2024;Supermercato;-50,00\n";
        $file = UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/transactions/import/sheets', [
            'csv_file' => $file,
        ]);

        // Il validator rifiuta file non-XLSX con 422
        $response->assertStatus(422);
    }

    #[Test]
    public function sheets_endpoint_requires_file_or_drive_params(): void
    {
        $response = $this->actingAs($this->user)->postJson('/transactions/import/sheets', []);

        $response->assertStatus(422);
    }

    #[Test]
    public function preview_with_sheet_index_is_accepted(): void
    {
        $csvContent = "Data;Descrizione;Importo\n01/01/2024;Supermercato;-50,00\n";
        $file = UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/transactions/import/preview', [
            'csv_file'     => $file,
            'bank_name'    => 'custom',
            'delimiter'    => ';',
            'date_format'  => 'd/m/Y',
            'has_header'   => true,
            'encoding'     => 'UTF-8',
            'sheet_index'  => 0,
            'column_mapping' => [
                'date'        => 0,
                'description' => 1,
                'amount'      => 2,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['total' => 1, 'valid_count' => 1]);
    }

    #[Test]
    public function preview_with_google_drive_missing_token_returns_validation_error(): void
    {
        $response = $this->actingAs($this->user)->postJson('/transactions/import/preview', [
            'google_drive_file_id' => 'some-file-id',
            // google_drive_access_token mancante
            'date_format'  => 'd/m/Y',
            'column_mapping' => ['date' => 0, 'amount' => 1, 'description' => 2],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['google_drive_access_token']);
    }

    #[Test]
    public function preview_with_google_drive_returns_error_on_failed_download(): void
    {
        // Simula una chiamata Google Drive che fallisce (access token non valido)
        Http::fake([
            'https://www.googleapis.com/*' => Http::response('Unauthorized', 401),
        ]);

        $response = $this->actingAs($this->user)->postJson('/transactions/import/preview', [
            'google_drive_file_id'      => 'fake-file-id',
            'google_drive_access_token' => 'fake-token',
            'google_drive_mime_type'    => 'text/csv',
            'date_format'               => 'd/m/Y',
            'column_mapping'            => ['date' => 0, 'amount' => 1, 'description' => 2],
        ]);

        $response->assertStatus(422);
    }
}
