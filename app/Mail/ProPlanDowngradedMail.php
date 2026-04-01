<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProPlanDowngradedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Il tuo piano Pro è scaduto — sei passato al piano Base',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.pro-plan-downgraded',
            with: [
                'user' => $this->user,
                'upgradeUrl' => route('profile.subscription'),
                // Feature list lette dinamicamente dal config per evitare duplicazioni
                'baseFeatures' => config('plans.plans.base.features', []),
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
