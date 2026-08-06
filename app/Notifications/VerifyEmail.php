<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends BaseVerifyEmail
{
    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Conferma il tuo indirizzo email')
            ->greeting('Ciao!')
            ->line('Per completare la registrazione, conferma il tuo indirizzo email cliccando sul pulsante qui sotto.')
            ->action('Conferma email', $verificationUrl)
            ->line('Se non hai creato un account, nessuna azione è richiesta.');
    }
}
