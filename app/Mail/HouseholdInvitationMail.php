<?php

namespace App\Mail;

use App\Models\HouseholdInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HouseholdInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public HouseholdInvitation $invitation,
        public bool $isNewUser = true
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Sei stato invitato a unirti a {$this->invitation->household->name} su Finanzamente",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.household-invitation',
            with: [
                'invitation' => $this->invitation,
                'householdName' => $this->invitation->household->name,
                'inviterName' => $this->invitation->invitedBy->name,
                'role' => $this->invitation->role === 'guest' ? 'Ospite' : 'Membro',
                'acceptUrl' => $this->getAcceptUrl(),
                'expiresAt' => $this->invitation->expires_at->format('d/m/Y H:i'),
                'isNewUser' => $this->isNewUser,
            ],
        );
    }

    /**
     * Get the accept URL based on whether the user is new or existing.
     */
    protected function getAcceptUrl(): string
    {
        if ($this->isNewUser) {
            return route('household-invitations.register', $this->invitation->token);
        }

        return route('household-invitations.accept', $this->invitation->token);
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
