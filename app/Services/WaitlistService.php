<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use SendinBlue\Client\Api\ContactsApi;
use SendinBlue\Client\Configuration;
use SendinBlue\Client\Model\CreateDoiContact;

/**
 * Servizio per la gestione della waitlist Pro tramite Brevo (ex Sendinblue).
 *
 * Gestisce:
 * - Generazione firma HMAC per identificazione early bird
 * - Iscrizione con double opt-in tramite API Brevo
 * - Salvataggio attributo SIGNATURE sul contatto Brevo
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
     * Iscrive un'email alla waitlist Pro su Brevo con double opt-in.
     * Salva la firma HMAC nell'attributo personalizzato SIGNATURE.
     * Non logga l'email in chiaro (GDPR compliant).
     */
    public function subscribe(string $email): bool
    {
        $apiKey = config('services.brevo.api_key');
        $listId = config('services.brevo.waitlist_list_id');
        $templateId = config('services.brevo.double_optin_template_id');
        $redirectUrl = config('services.brevo.double_optin_redirect_url');

        if (empty($apiKey) || empty($listId) || empty($templateId)) {
            Log::warning('Brevo waitlist: configurazione incompleta, iscrizione saltata.', [
                'has_api_key' => !empty($apiKey),
                'list_id' => $listId,
                'template_id' => $templateId,
            ]);

            return false;
        }

        $signature = $this->generateSignature($email);

        $config = Configuration::getDefaultConfiguration()
            ->setApiKey('api-key', $apiKey);

        $contactsApi = new ContactsApi(null, $config);

        $doiContact = new CreateDoiContact([
            'email'              => $email,
            'includeListIds'     => [$listId],
            'templateId'         => $templateId,
            'redirectionUrl'     => $redirectUrl ?: null,
            // NOTA: l'attributo personalizzato SIGNATURE deve essere creato manualmente
            // nel pannello Brevo prima di usare questa funzionalità.
            // Percorso: Contacts > Configuration > Contact Attributes > Create attribute (type: Text, name: SIGNATURE)
            'attributes'         => (object) ['SIGNATURE' => $signature],
        ]);

        try {
            $contactsApi->createDoiContact($doiContact);

            return true;
        } catch (\SendinBlue\Client\ApiException $e) {
            // Log senza email in chiaro (GDPR compliant): loghiamo solo codice e messaggio
            Log::error('Brevo waitlist: errore API durante iscrizione.', [
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
