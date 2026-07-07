<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Resources\Payment;

class MollieService
{
    private ?MollieApiClient $mollie = null;

    private function client(): MollieApiClient
    {
        if ($this->mollie === null) {
            $this->mollie = new MollieApiClient;
            $this->mollie->setApiKey(config('services.mollie.key'));
        }

        return $this->mollie;
    }

    /**
     * Crea o recupera il Customer Mollie per l'utente.
     */
    public function getOrCreateCustomer(User $user): Customer
    {
        if ($user->mollie_customer_id) {
            try {
                return $this->client()->customers->get($user->mollie_customer_id);
            } catch (\Throwable) {
                // Cliente non trovato su Mollie, ne creiamo uno nuovo
            }
        }

        $customer = $this->client()->customers->create([
            'name' => $user->name,
            'email' => $user->email,
            'locale' => 'it_IT',
            'metadata' => ['user_id' => $user->id],
        ]);

        $user->update(['mollie_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Crea il link di pagamento iniziale per attivare un abbonamento.
     * Mollie richiede un "first payment" tramite checkout per ottenere il mandate
     * (autorizzazione addebito ricorrente). I successivi pagamenti avvengono automaticamente.
     *
     * @param  Subscription  $subscription  Subscription già salvata con status=pending
     * @return string URL checkout Mollie
     */
    public function createFirstPaymentUrl(
        User $user,
        Subscription $subscription,
        string $redirectUrl,
        string $webhookUrl
    ): string {
        $customer = $this->getOrCreateCustomer($user);

        $planConfig = config("plans.plans.{$subscription->plan}");
        $description = $this->buildPaymentDescription($subscription, $planConfig);

        $payment = $this->client()->payments->create([
            'customerId' => $customer->id,
            'amount' => [
                'currency' => $subscription->currency,
                'value' => number_format($subscription->amount_cents / 100, 2, '.', ''),
            ],
            'description' => $description,
            'redirectUrl' => $redirectUrl,
            'webhookUrl' => $webhookUrl,
            'locale' => 'it_IT',
            'sequenceType' => 'first', // abilita addebiti ricorrenti futuri
            'metadata' => [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan' => $subscription->plan,
                'billing_cycle' => $subscription->billing_cycle,
            ],
        ]);

        return $payment->getCheckoutUrl();
    }

    /**
     * Crea un abbonamento ricorrente Mollie per un cliente con mandate attivo.
     */
    public function createSubscription(
        User $user,
        Subscription $subscription,
        string $webhookUrl
    ): \Mollie\Api\Resources\Subscription {
        $customer = $this->client()->customers->get($user->mollie_customer_id);

        $planConfig = config("plans.plans.{$subscription->plan}");
        $description = $this->buildPaymentDescription($subscription, $planConfig);

        $interval = $subscription->billing_cycle === 'annual' ? '12 months' : '1 month';

        $mollieSubscription = $customer->createSubscription([
            'amount' => [
                'currency' => $subscription->currency,
                'value' => number_format($subscription->amount_cents / 100, 2, '.', ''),
            ],
            'interval' => $interval,
            'description' => $description,
            'webhookUrl' => $webhookUrl,
            'locale' => 'it_IT',
            'metadata' => [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan' => $subscription->plan,
                'billing_cycle' => $subscription->billing_cycle,
            ],
        ]);

        $subscription->update([
            'mollie_subscription_id' => $mollieSubscription->id,
            'status' => 'active',
            'next_payment_at' => now()->add(
                $subscription->billing_cycle === 'annual' ? '12 months' : '1 month'
            ),
        ]);

        return $mollieSubscription;
    }

    /**
     * Cancella l'abbonamento ricorrente su Mollie.
     */
    public function cancelSubscription(Subscription $subscription): void
    {
        if (! $subscription->mollie_subscription_id || ! $subscription->user->mollie_customer_id) {
            return;
        }

        try {
            $customer = $this->client()->customers->get($subscription->user->mollie_customer_id);
            $customer->cancelSubscription($subscription->mollie_subscription_id);
        } catch (\Throwable) {
            // Se già cancellato su Mollie, ignoriamo l'errore
        }

        $subscription->update([
            'status' => 'cancelled',
            // ends_at = fine del periodo già pagato (non immediato)
            'ends_at' => $subscription->next_payment_at ?? now(),
        ]);
    }

    /**
     * Genera il link hosted per la modifica del metodo di pagamento.
     *
     * Mollie non fornisce un portal cliente standalone equivalente a Stripe Customer Portal.
     * La soluzione standard è creare un nuovo pagamento con sequenceType=first per un importo
     * simbolico (0,01 €) che registra un nuovo mandate (autorizzazione addebito futuro).
     *
     * Nota sull'addebito di 0,01 €:
     * - L'importo viene effettivamente addebitato al cliente come verifica del metodo di pagamento.
     * - Non viene rimborsato automaticamente da Mollie; se si desidera rimborsarlo è necessario
     *   emettere un refund separato tramite l'API Mollie dopo la verifica.
     * - La descrizione del pagamento visibile al cliente è "Aggiornamento metodo di pagamento".
     * - Una volta completato il pagamento, il webhook aggiorna il mollie_mandate_id sulla subscription,
     *   che verrà usato per i futuri rinnovi ricorrenti.
     */
    public function createUpdatePaymentMethodUrl(
        User $user,
        Subscription $subscription,
        string $redirectUrl,
        string $webhookUrl
    ): string {
        $customer = $this->getOrCreateCustomer($user);

        // Pagamento da 0,01€ per aggiornare il metodo di pagamento (mandate update)
        $payment = $this->client()->payments->create([
            'customerId' => $customer->id,
            'amount' => [
                'currency' => 'EUR',
                'value' => '0.01',
            ],
            'description' => 'Aggiornamento metodo di pagamento - Finanzamente',
            'redirectUrl' => $redirectUrl,
            'webhookUrl' => $webhookUrl,
            'locale' => 'it_IT',
            'sequenceType' => 'first',
            'metadata' => [
                'action' => 'update_payment_method',
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
            ],
        ]);

        return $payment->getCheckoutUrl();
    }

    /**
     * Verifica la firma webhook di Mollie (non esiste una firma standard,
     * si verifica tramite recupero diretto del payment/subscription da API).
     */
    public function getPayment(string $paymentId): Payment
    {
        return $this->client()->payments->get($paymentId);
    }

    /**
     * Riepilogo del metodo di pagamento attivo per un abbonamento Pro.
     *
     * @return array{method: string|null, label: string|null, last_digits: string|null, display: string|null}|null
     */
    public function getPaymentMethodSummary(User $user, Subscription $subscription): ?array
    {
        if (! $user->mollie_customer_id) {
            return null;
        }

        try {
            $customer = $this->client()->customers->get($user->mollie_customer_id);
            $mandate = $this->resolveActiveMandate($customer, $subscription);

            if (! $mandate) {
                return null;
            }

            return $this->formatMandateSummary($mandate);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveActiveMandate(Customer $customer, Subscription $subscription): ?Mandate
    {
        if ($subscription->mollie_mandate_id) {
            try {
                $mandate = $customer->getMandate($subscription->mollie_mandate_id);
                if ($mandate->status === 'valid') {
                    return $mandate;
                }
            } catch (\Throwable) {
                // fallback su mandate validi del customer
            }
        }

        $mandates = $customer->mandates();
        foreach ($mandates as $mandate) {
            if ($mandate->status === 'valid') {
                return $mandate;
            }
        }

        return null;
    }

    /**
     * @return array{method: string|null, label: string|null, last_digits: string|null, display: string|null}
     */
    private function formatMandateSummary(Mandate $mandate): array
    {
        $method = $mandate->method ?? null;
        $details = (array) ($mandate->details ?? []);
        $label = null;
        $lastDigits = null;

        if ($method === 'creditcard') {
            $label = $details['cardLabel'] ?? $details['card_label'] ?? 'Carta';
            $lastDigits = $details['cardNumber'] ?? $details['card_number'] ?? null;
            if (is_string($lastDigits) && strlen($lastDigits) > 4) {
                $lastDigits = substr($lastDigits, -4);
            }
        } elseif ($method === 'directdebit') {
            $label = 'Addebito diretto';
            $lastDigits = $details['consumerAccount'] ?? $details['consumer_account'] ?? null;
            if (is_string($lastDigits) && strlen($lastDigits) > 4) {
                $lastDigits = substr($lastDigits, -4);
            }
        } elseif (is_string($method)) {
            $label = ucfirst(str_replace('_', ' ', $method));
        }

        $display = $label;
        if ($lastDigits) {
            $display = trim(($label ?? 'Metodo').' •••• '.$lastDigits);
        }

        return [
            'method' => $method,
            'label' => $label,
            'last_digits' => $lastDigits,
            'display' => $display,
        ];
    }

    private function buildPaymentDescription(Subscription $subscription, array $planConfig): string
    {
        $planName = $planConfig['name'] ?? $subscription->plan;
        $cycle = $subscription->billing_cycle === 'annual' ? 'annuale' : 'mensile';

        return "Finanzamente {$planName} - Abbonamento {$cycle}";
    }
}
