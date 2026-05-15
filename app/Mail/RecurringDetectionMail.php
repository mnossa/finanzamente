<?php

namespace App\Mail;

use App\Models\Household;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecurringDetectionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Household $household,
        public int $suggestionsCount,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->suggestionsCount === 1
            ? 'Nuova ricorrenza suggerita su Finanzamente'
            : "{$this->suggestionsCount} nuove ricorrenze suggerite su Finanzamente";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.recurring-detection',
            with: [
                'user' => $this->user,
                'household' => $this->household,
                'suggestionsCount' => $this->suggestionsCount,
                'reviewUrl' => route('recurrence-detection.index'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
