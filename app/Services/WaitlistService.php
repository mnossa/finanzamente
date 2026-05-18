<?php

namespace App\Services;

use Brevo\Brevo;
use Brevo\Contacts\Requests\CreateContactRequest;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Servizio per la gestione della waitlist Pro tramite Brevo (ex Sendinblue).
 *
 * Gestisce:
 * - Generazione firma HMAC per identificazione early bird
 * - Iscrizione con double opt-in manuale tramite API Brevo
 * - Invio email di conferma transazionale con link firmato
 * - Aggiunta contatto alla lista Brevo dopo conferma
 */
class WaitlistService
{
    /**
     * Genera una firma HMAC basata sull'email, usando APP_KEY come segreto.
     * Utilizzata per identificare gli utenti early bird al momento della registrazione.
     */
    public function generateSignature(string $email): string
    {
        $secret = config('app.key');
        // Rimuove il prefisso "base64:" se presente (Laravel lo aggiunge alle chiavi base64)
        if (str_starts_with($secret, 'base64:')) {
            $secret = base64_decode(substr($secret, 7));
        }

        return hash_hmac('sha256', strtolower(trim($email)), $secret);
    }

    /**
     * Verifica che la firma fornita sia valida per l'email data.
     */
    public function verifySignature(string $email, string $signature): bool
    {
        return hash_equals($this->generateSignature($email), $signature);
    }

    /**
     * Iscrive un'email alla waitlist Pro su Brevo con double opt-in manuale.
     * Invia un'email transazionale con link di conferma firmato temporaneamente.
     * Il contatto viene aggiunto alla lista Brevo solo dopo la conferma.
     * Non logga l'email in chiaro (GDPR compliant).
     */
    public function subscribe(string $email): bool
    {
        if (! config('services.brevo.enabled', false)) {
            return false;
        }

        $apiKey = config('services.brevo.api_key');
        $templateId = config('services.brevo.double_optin_template_id');

        if (empty($apiKey) || empty($templateId)) {
            Log::warning('Brevo waitlist: configurazione incompleta, iscrizione saltata.', [
                'has_api_key' => ! empty($apiKey),
                'template_id' => $templateId,
            ]);

            return false;
        }

        // Genera URL firmato temporaneo (valido 7 giorni) con l'email come parametro
        $confirmUrl = URL::temporarySignedRoute(
            'waitlist.confirm',
            now()->addDays(7),
            ['email' => $email]
        );

        $brevo = new Brevo($apiKey);

        $sendEmail = new SendTransacEmailRequest([
            'to' => [new SendTransacEmailRequestToItem(['email' => $email])],
            'templateId' => (int) $templateId,
            'params' => ['CONFIRMATION_URL' => $confirmUrl],
        ]);

        try {
            $brevo->transactionalEmails->sendTransacEmail($sendEmail);

            return true;
        } catch (\Throwable $e) {
            Log::error('Brevo waitlist: errore invio email di conferma.', [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Conferma l'iscrizione alla waitlist: aggiunge il contatto alla lista Brevo.
     * Chiamato dopo che l'utente clicca il link di conferma nell'email.
     * Non logga l'email in chiaro (GDPR compliant).
     */
    public function confirmSubscription(string $email): bool
    {
        if (! config('services.brevo.enabled', false)) {
            return false;
        }

        $apiKey = config('services.brevo.api_key');
        $listId = config('services.brevo.waitlist_list_id');

        if (empty($apiKey) || empty($listId)) {
            Log::warning('Brevo waitlist: configurazione incompleta, conferma saltata.', [
                'has_api_key' => ! empty($apiKey),
                'list_id' => $listId,
            ]);

            return false;
        }

        $signature = $this->generateSignature($email);

        $brevo = new Brevo($apiKey);

        $contact = new CreateContactRequest([
            'email' => $email,
            'listIds' => [(int) $listId],
            'updateEnabled' => true,
            // NOTA: l'attributo personalizzato SIGNATURE deve essere creato manualmente
            // nel pannello Brevo: Contacts > Configuration > Contact Attributes > (type: Text, name: SIGNATURE)
            'attributes' => ['SIGNATURE' => $signature],
        ]);

        try {
            $brevo->contacts->createContact($contact);

            return true;
        } catch (\Throwable $e) {
            Log::error('Brevo waitlist: errore conferma iscrizione.', [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return false;
        }
    }
}
