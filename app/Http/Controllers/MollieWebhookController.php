<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use App\Services\MollieService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MollieWebhookController extends Controller
{
    public function __construct(private readonly MollieService $mollieService) {}

    /**
     * Gestisce i webhook inviati da Mollie.
     *
     * Mollie invia una POST con il solo campo `id` (payment ID o subscription ID).
     * Per verificarne l'autenticità, lo recuperiamo direttamente dalle API Mollie.
     *
     * Documentazione: https://docs.mollie.com/docs/webhooks
     */
    public function handle(Request $request): Response
    {
        $mollieId = $request->input('id');

        if (! $mollieId) {
            return response('', 200);
        }

        try {
            $this->processPaymentWebhook($mollieId);
        } catch (\Throwable $e) {
            Log::error('Mollie webhook error', [
                'mollie_id' => $mollieId,
                'error' => $e->getMessage(),
            ]);
            // Restituiamo sempre 200 per evitare che Mollie continui a reinviare
        }

        return response('', 200);
    }

    private function processPaymentWebhook(string $paymentId): void
    {
        $payment = $this->mollieService->getPayment($paymentId);

        $metadata = $payment->metadata ?? null;
        $subscriptionId = $metadata?->subscription_id ?? null;

        if (! $subscriptionId) {
            return;
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::find($subscriptionId);

        if (! $subscription) {
            Log::warning('Mollie webhook: subscription not found', ['subscription_id' => $subscriptionId]);

            return;
        }

        $action = $metadata?->action ?? null;

        if ($action === 'update_payment_method') {
            $this->handlePaymentMethodUpdate($payment, $subscription);

            return;
        }

        match ($payment->status) {
            'paid' => $this->handlePaidPayment($payment, $subscription),
            'failed', 'canceled', 'expired' => $this->handleFailedPayment($payment, $subscription),
            default => null,
        };
    }

    private function handlePaidPayment(
        \Mollie\Api\Resources\Payment $payment,
        Subscription $subscription
    ): void {
        $user = $subscription->user;

        // Salva il mandate ID per i pagamenti ricorrenti futuri
        if ($payment->mandateId) {
            $subscription->update(['mollie_mandate_id' => $payment->mandateId]);
        }

        // Se è il primo pagamento (sequenceType=first), attiva l'abbonamento ricorrente
        if ($payment->sequenceType === 'first' && $subscription->status === 'pending') {
            try {
                $webhookUrl = route('mollie.webhook');
                $this->mollieService->createSubscription($user, $subscription, $webhookUrl);
            } catch (\Throwable $e) {
                Log::error('Mollie: failed to create subscription after first payment', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Attiva il piano Pro sull'utente
        $user->update(['plan' => $subscription->plan]);

        // Aggiorna la subscription come attiva
        $subscription->update(['status' => 'active']);

        Log::info('Mollie: subscription activated', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'plan' => $subscription->plan,
        ]);
    }

    private function handleFailedPayment(
        \Mollie\Api\Resources\Payment $payment,
        Subscription $subscription
    ): void {
        if ($subscription->status === 'pending') {
            $subscription->update(['status' => 'cancelled']);
        }

        Log::warning('Mollie: payment failed', [
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
            'status' => $payment->status,
        ]);
    }

    private function handlePaymentMethodUpdate(
        \Mollie\Api\Resources\Payment $payment,
        Subscription $subscription
    ): void {
        if ($payment->status === 'paid' && $payment->mandateId) {
            $subscription->update(['mollie_mandate_id' => $payment->mandateId]);

            Log::info('Mollie: payment method updated', [
                'subscription_id' => $subscription->id,
                'mandate_id' => $payment->mandateId,
            ]);
        }
    }
}
