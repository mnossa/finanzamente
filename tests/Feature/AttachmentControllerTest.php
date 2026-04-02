<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Attachment;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;
    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        Storage::fake('private');

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true, 'can-modify' => true])]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
        ]);

        $this->transaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_tax_deductible' => true,
            'tax_deduction_rate' => 19.00,
            'tax_deduction_type' => 'mediche',
            'tax_year' => 2024,
        ]);
    }

    #[Test]
    public function user_can_upload_attachment_to_transaction()
    {
        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)->postJson('/allegati', [
            'attachable_type' => 'Transaction',
            'attachable_id' => $this->transaction->id,
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'attachment' => ['id', 'filename', 'mime_type', 'file_size', 'uploaded_at'],
            ]);

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'filename' => 'receipt.pdf',
            'uploaded_by' => $this->user->id,
        ]);

        // Verifica che il file sia stato salvato
        $attachment = Attachment::where('attachable_id', $this->transaction->id)->first();
        Storage::disk('private')->assertExists($attachment->file_path);
    }

    #[Test]
    public function user_can_upload_image_attachment()
    {
        if (!extension_loaded('gd') || !function_exists('imagejpeg')) {
            $this->markTestSkipped('L\'estensione GD non è disponibile');
        }

        $file = UploadedFile::fake()->image('receipt.jpg')->size(500);

        $response = $this->actingAs($this->user)->postJson('/allegati', [
            'attachable_type' => 'Transaction',
            'attachable_id' => $this->transaction->id,
            'file' => $file,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('attachments', [
            'filename' => 'receipt.jpg',
            'mime_type' => 'image/jpeg',
        ]);
    }

    #[Test]
    public function upload_requires_file()
    {
        $response = $this->actingAs($this->user)->postJson('/allegati', [
            'attachable_type' => 'Transaction',
            'attachable_id' => $this->transaction->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function upload_requires_attachable_type_and_id()
    {
        $file = UploadedFile::fake()->create('receipt.pdf', 100);

        $response = $this->actingAs($this->user)->postJson('/allegati', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attachable_type', 'attachable_id']);
    }

    #[Test]
    public function upload_enforces_file_size_limit()
    {
        $file = UploadedFile::fake()->create('large-file.pdf', 6000); // 6MB

        $response = $this->actingAs($this->user)->postJson('/allegati', [
            'attachable_type' => 'Transaction',
            'attachable_id' => $this->transaction->id,
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function upload_enforces_file_type_restrictions()
    {
        $file = UploadedFile::fake()->create('script.exe', 100);

        $response = $this->actingAs($this->user)->postJson('/allegati', [
            'attachable_type' => 'Transaction',
            'attachable_id' => $this->transaction->id,
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function user_cannot_upload_attachment_to_transaction_from_different_household()
    {
        $otherHousehold = Household::factory()->create();
        $otherAccount = Account::factory()->create(['household_id' => $otherHousehold->id]);
        $otherTransaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $otherAccount->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
        ]);

        $file = UploadedFile::fake()->create('receipt.pdf', 100);

        $response = $this->actingAs($this->user)->postJson('/allegati', [
            'attachable_type' => 'Transaction',
            'attachable_id' => $otherTransaction->id,
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function user_cannot_upload_attachment_to_private_transaction_of_others()
    {
        $otherUser = User::factory()->create();
        $this->household->users()->attach($otherUser->id, ['role' => 'member', 'permissions' => json_encode([])]);

        $privateTransaction = Transaction::create([
            'user_id' => $otherUser->id,
            'account_id' => $this->account->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
            'is_private' => true,
        ]);

        $file = UploadedFile::fake()->create('receipt.pdf', 100);

        $response = $this->actingAs($this->user)->postJson('/allegati', [
            'attachable_type' => 'Transaction',
            'attachable_id' => $privateTransaction->id,
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function user_can_download_attachment()
    {
        Storage::disk('private')->put('attachments/test.pdf', 'test content');

        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get("/allegati/{$attachment->id}/scarica");

        $response->assertStatus(200);
        $response->assertDownload('receipt.pdf');
    }

    #[Test]
    public function download_returns_404_if_file_not_exists()
    {
        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/nonexistent.pdf',
            'filename' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get("/allegati/{$attachment->id}/scarica");

        $response->assertStatus(404);
    }

    #[Test]
    public function user_cannot_download_attachment_from_different_household()
    {
        $otherHousehold = Household::factory()->create();
        $otherAccount = Account::factory()->create(['household_id' => $otherHousehold->id]);
        $otherTransaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $otherAccount->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
        ]);

        Storage::disk('private')->put('attachments/test.pdf', 'test content');

        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $otherTransaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get("/allegati/{$attachment->id}/scarica");

        $response->assertStatus(403);
    }

    #[Test]
    public function user_can_delete_attachment()
    {
        Storage::disk('private')->put('attachments/test.pdf', 'test content');

        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/allegati/{$attachment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('attachments', ['id' => $attachment->id]);
        Storage::disk('private')->assertMissing('attachments/test.pdf');
    }

    #[Test]
    public function user_cannot_delete_attachment_from_different_household()
    {
        $otherHousehold = Household::factory()->create();
        $otherAccount = Account::factory()->create(['household_id' => $otherHousehold->id]);
        $otherTransaction = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $otherAccount->id,
            'amount' => -100.00,
            'date' => now(),
            'currency_code' => 'EUR',
        ]);

        Storage::disk('private')->put('attachments/test.pdf', 'test content');

        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $otherTransaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/allegati/{$attachment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id, 'deleted_at' => null]);
    }

    #[Test]
    public function guest_cannot_upload_attachments()
    {
        $file = UploadedFile::fake()->create('receipt.pdf', 100);

        $response = $this->postJson('/allegati', [
            'attachable_type' => 'Transaction',
            'attachable_id' => $this->transaction->id,
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function guest_cannot_download_attachments()
    {
        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->get("/allegati/{$attachment->id}/scarica");

        $response->assertStatus(302); // Redirect to login
    }

    #[Test]
    public function guest_cannot_delete_attachments()
    {
        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'receipt.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/allegati/{$attachment->id}");

        $response->assertStatus(401);
    }

    #[Test]
    public function attachment_filename_is_sanitized_on_upload()
    {
        $file = UploadedFile::fake()->create('Ricevuta Medica 2024.pdf', 100);

        $response = $this->actingAs($this->user)->postJson('/allegati', [
            'attachable_type' => 'Transaction',
            'attachable_id' => $this->transaction->id,
            'file' => $file,
        ]);

        $response->assertStatus(201);

        $attachment = Attachment::where('attachable_id', $this->transaction->id)->first();
        
        // Il filename originale deve essere preservato
        $this->assertEquals('Ricevuta Medica 2024.pdf', $attachment->filename);
        
        // Ma il file_path deve essere sanitizzato
        $this->assertStringContainsString('ricevuta-medica-2024', $attachment->file_path);
    }
}
