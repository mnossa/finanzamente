<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Attachment;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;
    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, ['role' => 'owner', 'permissions' => json_encode(['manage' => true])]);
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
    public function it_can_create_attachment_with_all_fields()
    {
        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('attachments', [
            'id' => $attachment->id,
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);
    }

    #[Test]
    public function it_has_attachable_polymorphic_relationship()
    {
        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Transaction::class, $attachment->attachable);
        $this->assertEquals($this->transaction->id, $attachment->attachable->id);
    }

    #[Test]
    public function it_has_uploader_relationship()
    {
        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $attachment->uploader);
        $this->assertEquals($this->user->id, $attachment->uploader->id);
    }

    #[Test]
    public function it_can_attach_to_different_models()
    {
        // Attachment a Transaction
        $transactionAttachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/transaction.pdf',
            'filename' => 'transaction.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Transaction::class, $transactionAttachment->attachable);
    }

    #[Test]
    public function it_stores_file_metadata_correctly()
    {
        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/2024-02-16_123456_report.pdf',
            'filename' => 'Financial Report 2024.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 524288, // 512 KB
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $this->assertEquals('attachments/2024-02-16_123456_report.pdf', $attachment->file_path);
        $this->assertEquals('Financial Report 2024.pdf', $attachment->filename);
        $this->assertEquals('application/pdf', $attachment->mime_type);
        $this->assertEquals(524288, $attachment->file_size);
    }

    #[Test]
    public function it_supports_different_file_types()
    {
        $fileTypes = [
            ['filename' => 'document.pdf', 'mime_type' => 'application/pdf'],
            ['filename' => 'image.jpg', 'mime_type' => 'image/jpeg'],
            ['filename' => 'screenshot.png', 'mime_type' => 'image/png'],
            ['filename' => 'report.doc', 'mime_type' => 'application/msword'],
            ['filename' => 'spreadsheet.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ];

        foreach ($fileTypes as $fileType) {
            $attachment = Attachment::create([
                'attachable_type' => Transaction::class,
                'attachable_id' => $this->transaction->id,
                'file_path' => 'attachments/' . $fileType['filename'],
                'filename' => $fileType['filename'],
                'mime_type' => $fileType['mime_type'],
                'file_size' => 1024,
                'uploaded_at' => now(),
                'uploaded_by' => $this->user->id,
            ]);

            $this->assertEquals($fileType['mime_type'], $attachment->mime_type);
        }
    }

    #[Test]
    public function it_uses_soft_deletes()
    {
        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $attachmentId = $attachment->id;
        $attachment->delete();

        // Soft delete: il record esiste ancora ma con deleted_at
        $this->assertSoftDeleted('attachments', ['id' => $attachmentId]);

        // Non trovabile con query standard
        $this->assertNull(Attachment::find($attachmentId));

        // Trovabile con withTrashed
        $this->assertNotNull(Attachment::withTrashed()->find($attachmentId));
    }

    #[Test]
    public function it_casts_uploaded_at_as_date()
    {
        $now = now();
        $attachment = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/test.pdf',
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => $now,
            'uploaded_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $attachment->uploaded_at);
        $this->assertEquals($now->toDateString(), $attachment->uploaded_at->toDateString());
    }

    #[Test]
    public function it_has_fillable_attributes()
    {
        $fillable = ['attachable_type', 'attachable_id', 'file_path', 'filename', 'mime_type', 'file_size', 'uploaded_at', 'uploaded_by'];

        $attachment = new Attachment();
        $this->assertEquals($fillable, $attachment->getFillable());
    }

    #[Test]
    public function multiple_attachments_can_belong_to_same_transaction()
    {
        $attachment1 = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/file1.pdf',
            'filename' => 'file1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $attachment2 = Attachment::create([
            'attachable_type' => Transaction::class,
            'attachable_id' => $this->transaction->id,
            'file_path' => 'attachments/file2.jpg',
            'filename' => 'file2.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
            'uploaded_at' => now(),
            'uploaded_by' => $this->user->id,
        ]);

        $attachments = Attachment::where('attachable_id', $this->transaction->id)
            ->where('attachable_type', Transaction::class)
            ->get();

        $this->assertCount(2, $attachments);
    }
}
