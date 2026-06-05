<?php

namespace App\Mail;

use App\Models\InvestmentPac;
use App\Models\User;
use App\Services\InvestmentPacReminderFormatter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvestmentPacReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $details;

    public function __construct(
        public User $user,
        public InvestmentPac $pac,
        public Carbon $dueDate,
    ) {
        $this->details = app(InvestmentPacReminderFormatter::class)->format($pac, $dueDate);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Promemoria PAC — '.$this->details['asset_name'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.investment-pac-reminder',
            with: [
                'user' => $this->user,
                'pac' => $this->pac,
                'dueDate' => $this->dueDate,
                'details' => $this->details,
            ],
        );
    }
}
