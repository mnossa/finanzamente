<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;

class MollieService
{
    private ?MollieApiClient $mollie = null;

    private function client(): MollieApiClient
    {
        if ($this->mollie === null) {
            $this->mollie = new MollieApiClient();
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
     * @param  User    $user
     * @param  Subscription $subscription  Subscription già salvata con status=pending
     * @param  string  $redirectUrl
     * @param  string  $webhookUrl
     * @return string  URL checkout Mollie
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
            'ends_at' => now(),
        ]);
    }

    /**
     * Genera il link hosted per la modifica del metodo di pagamento (Mollie Dashboard Link).
     * Mollie non fornisce un portal cliente standalone, ma è possibile creare un nuovo
     * pagamento con sequenceType=first per aggiornare il mandate.
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
            'description' => 'Aggiornamento metodo di pagamento - FinanzaMente',
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
    public function getPayment(string $paymentId): \Mollie\Api\Resources\Payment
    {
        return $this->client()->payments->get($paymentId);
    }

    private function buildPaymentDescription(Subscription $subscription, array $planConfig): string
    {
        $planName = $planConfig['name'] ?? $subscription->plan;
        $cycle = $subscription->billing_cycle === 'annual' ? 'annuale' : 'mensile';

        return "FinanzaMente {$planName} - Abbonamento {$cycle}";
    }
}
