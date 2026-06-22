<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UpcomingDueWeeklyMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array<string, mixed>>  $movements
     */
    public function __construct(
        public User $user,
        public array $movements,
        public string $summaryMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Riepilogo prossime scadenze — questa settimana',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.upcoming-due-weekly',
        );
    }
}
