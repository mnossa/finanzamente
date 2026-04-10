<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use SendinBlue\Client\Api\ContactsApi;
use SendinBlue\Client\Api\TransactionalEmailsApi;
use SendinBlue\Client\Configuration;
use SendinBlue\Client\Model\CreateContact;
use SendinBlue\Client\Model\SendSmtpEmail;
use SendinBlue\Client\Model\SendSmtpEmailTo;

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
        $apiKey     = config('services.brevo.api_key');
        $templateId = config('services.brevo.double_optin_template_id');

        if (empty($apiKey) || empty($templateId)) {
            Log::warning('Brevo waitlist: configurazione incompleta, iscrizione saltata.', [
                'has_api_key'  => !empty($apiKey),
                'template_id'  => $templateId,
            ]);

            return false;
        }

        // Genera URL firmato temporaneo (valido 7 giorni) con l'email come parametro
        $confirmUrl = URL::temporarySignedRoute(
            'waitlist.confirm',
            now()->addDays(7),
            ['email' => $email]
        );

        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', $apiKey);

        $emailsApi = new TransactionalEmailsApi(null, $config);

        $sendEmail = new SendSmtpEmail([
            'to'         => [new SendSmtpEmailTo(['email' => $email])],
            'templateId' => (int) $templateId,
            'params'     => ['CONFIRMATION_URL' => $confirmUrl],
        ]);

        try {
            $emailsApi->sendTransacEmail($sendEmail);

            return true;
        } catch (\Throwable $e) {
            Log::error('Brevo waitlist: errore invio email di conferma.', [
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
                'class'   => get_class($e),
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
        $apiKey    = config('services.brevo.api_key');
        $listId    = config('services.brevo.waitlist_list_id');

        if (empty($apiKey) || empty($listId)) {
            Log::warning('Brevo waitlist: configurazione incompleta, conferma saltata.', [
                'has_api_key' => !empty($apiKey),
                'list_id'     => $listId,
            ]);

            return false;
        }

        $signature = $this->generateSignature($email);

        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', $apiKey);

        $contactsApi = new ContactsApi(null, $config);

        $contact = new CreateContact([
            'email'         => $email,
            'listIds'       => [(int) $listId],
            'updateEnabled' => true,
            // NOTA: l'attributo personalizzato SIGNATURE deve essere creato manualmente
            // nel pannello Brevo: Contacts > Configuration > Contact Attributes > (type: Text, name: SIGNATURE)
            'attributes'    => (object) ['SIGNATURE' => $signature],
        ]);

        try {
            $contactsApi->createContact($contact);

            return true;
        } catch (\Throwable $e) {
            Log::error('Brevo waitlist: errore conferma iscrizione.', [
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
                'class'   => get_class($e),
            ]);

            return false;
        }
    }
}
