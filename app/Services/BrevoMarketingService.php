<?php

namespace App\Services;

use Brevo\Brevo;
use Brevo\Contacts\Requests\CreateContactRequest;
use Illuminate\Support\Facades\Log;

class BrevoMarketingService
{
    public function syncMarketingConsent(string $email, bool $isGranted): void
    {
        $apiKey = config('services.brevo.api_key');
        if (empty($apiKey)) {
            return;
        }

        $listId = (int) config('services.brevo.marketing_list_id', 0);

        $payload = [
            'email' => strtolower(trim($email)),
            'updateEnabled' => true,
            'attributes' => [
                'MARKETING_OPT_IN' => $isGranted,
            ],
        ];

        if ($isGranted && $listId > 0) {
            $payload['listIds'] = [$listId];
        }

        if (! $isGranted && $listId > 0) {
            $payload['unlinkListIds'] = [$listId];
        }

        try {
            $brevo = new Brevo($apiKey);
            $contact = new CreateContactRequest($payload);
            $brevo->contacts->createContact($contact);
        } catch (\Throwable $e) {
            Log::warning('Brevo marketing sync fallito.', [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
