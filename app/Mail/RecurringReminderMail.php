<?php

namespace App\Mail;

use App\Models\RecurringTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecurringReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public RecurringTransaction $recurringTransaction,
        public Carbon $dueDate,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Promemoria: transazione ricorrente in scadenza domani',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.recurring-reminder',
        );
    }
}
