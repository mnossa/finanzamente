<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends BaseResetPassword
{
    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Reimposta la tua password')
            ->greeting('Ciao!')
            ->line('Hai ricevuto questa email perché abbiamo ricevuto una richiesta di reimpostazione della password per il tuo account.')
            ->action('Reimposta Password', $this->resetUrl($notifiable))
            ->line('Questo link scadrà tra '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minuti.')
            ->line('Se non hai richiesto la reimpostazione della password, non è necessaria alcuna azione.');
    }
}
