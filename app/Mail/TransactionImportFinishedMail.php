<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionImportFinishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public bool $successful,
        public string $notificationTitle,
        public string $notificationMessage,
        public ?string $errorDetail = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->successful
            ? 'Importazione transazioni completata'
            : 'Importazione transazioni non riuscita';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.transaction-import-finished',
            with: [
                'user' => $this->user,
                'successful' => $this->successful,
                'notificationTitle' => $this->notificationTitle,
                'notificationMessage' => $this->notificationMessage,
                'errorDetail' => $this->errorDetail,
                'transactionsUrl' => route('transactions.index'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
