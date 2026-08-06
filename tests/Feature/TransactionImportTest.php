<?php

namespace Tests\Feature;

use App\Mail\TransactionImportFinishedMail;
use App\Models\Account;
use App\Models\BankImportLayout;
use App\Models\Currency;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RecurrenceDetectionService;
use App\Services\TransactionImportService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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

        // Forza la coda in modalità sync per i test
        $this->app['config']->set('queue.default', 'sync');

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
        $response = $this->get('/transazioni/importa');

        $response->assertRedirect('/accedi');
    }

    #[Test]
    public function user_can_preview_csv(): void
    {
        $csvContent = "Data;Descrizione;Importo\n01/01/2024;Supermercato;-50,00\n05/01/2024;Stipendio;1500,00\n";
        $file = UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa/anteprima', [
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
        $response->assertJsonStructure(['headers', 'valid', 'invalid', 'total', 'valid_count', 'invalid_count', 'mapping_warnings']);
        $response->assertJson(['total' => 2, 'valid_count' => 2, 'invalid_count' => 0]);
        $this->assertSame([], $response->json('mapping_warnings'));
    }

    #[Test]
    public function preview_rejects_invalid_csv_format(): void
    {
        $file = UploadedFile::fake()->create('transactions.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa/anteprima', [
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
        $response = $this->actingAs($this->user)->postJson('/transazioni/importa', [
            'account_id' => $this->account->id,
            'rows' => [
                ['date' => '2024-01-01', 'amount' => -50.00, 'description' => 'Supermercato', 'notes' => null],
                ['date' => '2024-01-05', 'amount' => 1500.00, 'description' => 'Stipendio', 'notes' => null],
            ],
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseCount('transactions', 2);
        // Senza category_mappings il sign è determinato dall'abs: le spese/entrate sono
        // riconosciute come positive finché non si associa una categoria expense/income.
        $this->assertDatabaseHas('transactions', ['description' => 'Supermercato', 'amount' => 50.00]);
        $this->assertDatabaseHas('transactions', ['description' => 'Stipendio', 'amount' => 1500.00]);
    }

    #[Test]
    public function duplicate_check_matches_row_and_db_when_only_sign_differs(): void
    {
        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'date' => '2024-06-01',
            'amount' => -50.00,
            'description' => 'Spesa',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('transactions.import.check-duplicates'), [
            'account_id' => $this->account->id,
            'rows' => [
                ['date' => '2024-06-01', 'amount' => 50.00, 'description' => 'Spesa da CSV'],
            ],
        ]);

        $response->assertOk();
        $dups = $response->json('duplicates');
        $this->assertCount(1, $dups);
        $this->assertSame(0, $dups[0]['row_index']);
    }

    #[Test]
    public function import_requires_valid_account(): void
    {
        $response = $this->actingAs($this->user)->postJson('/transazioni/importa', [
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
        $response = $this->actingAs($this->user)->postJson('/transazioni/importa', [
            'account_id' => $this->account->id,
            'rows' => [],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function user_can_save_custom_layout(): void
    {
        $response = $this->actingAs($this->user)->postJson('/layout-banca', [
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
    public function store_layout_persists_account_column_in_column_mapping(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('bank-import-layouts.store'), [
            'name' => 'Layout con colonna conto',
            'bank_name' => 'custom',
            'icon' => 'csv',
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'date' => 0,
                'amount' => 2,
                'description' => 1,
                'notes' => null,
                'account' => 3,
            ],
        ]);

        $response->assertOk();
        $layout = BankImportLayout::where('name', 'Layout con colonna conto')->first();
        $this->assertNotNull($layout);
        $this->assertSame(3, $layout->column_mapping['account']);
    }

    #[Test]
    public function import_dispatches_completion_email(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa', [
            'account_id' => $this->account->id,
            'rows' => [
                ['date' => '2024-01-01', 'amount' => -50.00, 'description' => 'Test email', 'notes' => null],
            ],
        ]);

        $response->assertStatus(302);
        Mail::assertSent(TransactionImportFinishedMail::class, function (TransactionImportFinishedMail $mail): bool {
            return $mail->successful === true
                && str_contains($mail->notificationMessage, '1')
                && str_contains($mail->notificationMessage, 'transazione');
        });
    }

    #[Test]
    public function user_cannot_delete_another_users_layout(): void
    {
        $otherUser = User::factory()->create();
        $layout = BankImportLayout::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)->delete("/layout-banca/{$layout->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function sheets_endpoint_rejects_non_xlsx_file(): void
    {
        $csvContent = "Data;Descrizione;Importo\n01/01/2024;Supermercato;-50,00\n";
        $file = UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa/fogli', [
            'csv_file' => $file,
        ]);

        // Il validator rifiuta file non-XLSX con 422
        $response->assertStatus(422);
    }

    #[Test]
    public function sheets_endpoint_requires_file_or_drive_params(): void
    {
        $response = $this->actingAs($this->user)->postJson('/transazioni/importa/fogli', []);

        $response->assertStatus(422);
    }

    #[Test]
    public function preview_with_sheet_index_is_accepted(): void
    {
        $csvContent = "Data;Descrizione;Importo\n01/01/2024;Supermercato;-50,00\n";
        $file = UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa/anteprima', [
            'csv_file' => $file,
            'bank_name' => 'custom',
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'sheet_index' => 0,
            'column_mapping' => [
                'date' => 0,
                'description' => 1,
                'amount' => 2,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['total' => 1, 'valid_count' => 1]);
    }

    #[Test]
    public function preview_with_google_drive_missing_token_returns_validation_error(): void
    {
        $response = $this->actingAs($this->user)->postJson('/transazioni/importa/anteprima', [
            'google_drive_file_id' => 'some-file-id',
            // google_drive_access_token mancante
            'date_format' => 'd/m/Y',
            'column_mapping' => ['date' => 0, 'amount' => 1, 'description' => 2],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['google_drive_access_token']);
    }

    #[Test]
    public function preview_returns_mapping_warning_when_description_points_to_category_header(): void
    {
        $csvContent = "Data;Importo;Categoria\n01/01/2024;-10,00;Abbonamento\n";
        $file = UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa/anteprima', [
            'csv_file' => $file,
            'bank_name' => 'custom',
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => [
                'date' => 0,
                'amount' => 1,
                'description' => 2,
            ],
        ]);

        $response->assertStatus(200);
        $warnings = $response->json('mapping_warnings');
        $this->assertIsArray($warnings);
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Categoria', implode(' ', $warnings));
    }

    #[Test]
    public function preview_csv_with_currency_column_returns_unique_currencies(): void
    {
        $csvContent = "Data;Descrizione;Importo;Valuta\n01/01/2024;Supermercato;-50,00;EUR\n05/01/2024;Hotel NY;-120,00;USD\n06/01/2024;Pub London;-30,00;GBP\n";
        $file = UploadedFile::fake()->createWithContent('transactions.csv', $csvContent);

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa/anteprima', [
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
                'currency' => 3,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['total' => 3, 'valid_count' => 3]);
        $response->assertJsonStructure(['unique_currencies']);
        $currencies = $response->json('unique_currencies');
        $this->assertContains('EUR', $currencies);
        $this->assertContains('USD', $currencies);
        $this->assertContains('GBP', $currencies);
    }

    #[Test]
    public function import_with_default_currency_sets_currency_on_transactions(): void
    {
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€']);

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa', [
            'account_id' => $this->account->id,
            'default_currency' => 'EUR',
            'rows' => [
                ['date' => '2024-01-01', 'amount' => -50.00, 'description' => 'Supermercato'],
                ['date' => '2024-01-05', 'amount' => 1500.00, 'description' => 'Stipendio'],
            ],
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', [
            'description' => 'Supermercato',
            'currency_code' => $this->account->currency_code,
        ]);
    }

    #[Test]
    public function import_with_per_row_currency_code_creates_transactions(): void
    {
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€']);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$']);

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa', [
            'account_id' => $this->account->id,
            'default_currency' => 'EUR',
            'rows' => [
                ['date' => '2024-01-01', 'amount' => -50.00, 'description' => 'Locale EUR'],
                ['date' => '2024-01-05', 'amount' => -120.00, 'description' => 'Hotel USD', 'currency_code' => 'USD'],
            ],
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseHas('transactions', [
            'description' => 'Locale EUR',
            'original_currency_code' => null,
        ]);
        $tx = Transaction::where('description', 'Hotel USD')->first();
        $this->assertNotNull($tx);
        $this->assertEquals('USD', $tx->original_currency_code);
    }

    #[Test]
    public function import_sotto_soglia_non_avvia_rilevamento_ricorrenze_automatico(): void
    {
        $mock = $this->mock(RecurrenceDetectionService::class);
        $mock->shouldNotReceive('detectForHousehold');

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa', [
            'account_id' => $this->account->id,
            'rows' => [
                ['date' => '2024-01-01', 'amount' => -10.00, 'description' => 'Riga 1'],
                ['date' => '2024-01-02', 'amount' => -20.00, 'description' => 'Riga 2'],
            ],
        ]);

        $response->assertStatus(302);
    }

    #[Test]
    public function import_significativo_avvia_rilevamento_ricorrenze_automatico(): void
    {
        $mock = $this->mock(RecurrenceDetectionService::class);
        $mock->shouldReceive('detectForHousehold')
            ->once()
            ->with($this->household->id)
            ->andReturn(0);

        $rows = [];
        for ($i = 0; $i < 200; $i++) {
            $rows[] = [
                'date' => '2024-01-01',
                'amount' => -10.00 - $i,
                'description' => 'Import bulk '.$i,
            ];
        }

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa', [
            'account_id' => $this->account->id,
            'rows' => $rows,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseCount('transactions', 200);
    }

    #[Test]
    public function layout_can_include_currency_column_mapping(): void
    {
        $response = $this->actingAs($this->user)->postJson('/layout-banca', [
            'name' => 'Layout multi-valuta',
            'bank_name' => 'custom',
            'delimiter' => ';',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'encoding' => 'UTF-8',
            'column_mapping' => ['date' => 0, 'description' => 1, 'amount' => 2, 'notes' => null, 'currency' => 3],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bank_import_layouts', [
            'user_id' => $this->user->id,
            'name' => 'Layout multi-valuta',
        ]);
        $layout = BankImportLayout::where('name', 'Layout multi-valuta')->first();
        $this->assertEquals(3, $layout->column_mapping['currency']);
    }

    #[Test]
    public function preview_with_google_drive_returns_error_on_failed_download(): void
    {
        // Simula una chiamata Google Drive che fallisce (access token non valido)
        Http::fake([
            'https://www.googleapis.com/*' => Http::response('Unauthorized', 401),
        ]);

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa/anteprima', [
            'google_drive_file_id' => 'fake-file-id',
            'google_drive_access_token' => 'fake-token',
            'google_drive_mime_type' => 'text/csv',
            'date_format' => 'd/m/Y',
            'column_mapping' => ['date' => 0, 'amount' => 1, 'description' => 2],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function preview_returns_user_friendly_message_when_parser_throws_unexpected_error(): void
    {
        $this->mock(TransactionImportService::class, function ($mock): void {
            $mock->shouldReceive('parseCsv')
                ->once()
                ->andThrow(new \Error('boom'));
        });

        $file = UploadedFile::fake()->createWithContent('transactions.csv', "Data;Descrizione;Importo\n01/01/2024;Test;10,00\n");

        $response = $this->actingAs($this->user)->postJson('/transazioni/importa/anteprima', [
            'csv_file' => $file,
            'bank_name' => 'custom',
            'delimiter' => ';',
            'encoding' => 'UTF-8',
            'date_format' => 'd/m/Y',
            'has_header' => true,
            'column_mapping' => [
                'date' => 0,
                'description' => 1,
                'amount' => 2,
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Errore durante la lettura del file. Verifica il tracciato e riprova.',
        ]);
    }
}
