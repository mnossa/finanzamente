<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProPlanExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public int $daysRemaining,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->daysRemaining === 1
            ? 'Il tuo piano Pro scade domani — rinnova ora'
            : "Il tuo piano Pro scade tra {$this->daysRemaining} giorni";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.pro-plan-expiring',
            with: [
                'user' => $this->user,
                'daysRemaining' => $this->daysRemaining,
                'expiresAt' => $this->user->plan_expires_at->format('d/m/Y'),
                'renewUrl' => route('profile.subscription'),
                // Feature list lette dinamicamente dal config per evitare duplicazioni
                'proFeatures' => collect(config('plans.plans.pro.features', []))
                    ->reject(fn ($f) => $f === 'Tutto del piano Base')
                    ->values()
                    ->all(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
