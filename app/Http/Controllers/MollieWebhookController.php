<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\MollieService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mollie\Api\Resources\Payment;

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
        $configuredSecret = (string) config('services.mollie.webhook_secret', '');
        if ($configuredSecret !== '') {
            $receivedSecret = (string) $request->header('X-Mollie-Webhook-Secret', '');
            if (! hash_equals($configuredSecret, $receivedSecret)) {
                Log::warning('Mollie webhook rejected: invalid secret header', [
                    'ip' => $request->ip(),
                ]);

                return response('', 401);
            }
        }

        $mollieId = $request->input('id');

        if (! $mollieId) {
            if (app()->environment('e2e') && $request->boolean('e2e_mock')) {
                $this->processE2eMockWebhook($request);
            }

            return response('', 200);
        }

        // Idempotenza: evitiamo doppi effetti in caso di retry webhook.
        $cacheKey = 'mollie_webhook:id:'.$mollieId;
        if (! Cache::add($cacheKey, now()->timestamp, now()->addHours(12))) {
            return response('', 200);
        }

        try {
            $this->processPaymentWebhook($mollieId);
        } catch (\Throwable $e) {
            // Logghiamo e segnaliamo l'eccezione per il debug, ma restituiamo sempre 200
            // per evitare che Mollie continui a re-inviare il webhook (best practice Mollie).
            report($e);
            Log::error('Mollie webhook error', [
                'mollie_id' => $mollieId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response('', 200);
    }

    private function processE2eMockWebhook(Request $request): void
    {
        $subscriptionId = (int) $request->input('subscription_id', 0);
        $status = (string) $request->input('status', 'paid');
        $sequenceType = (string) $request->input('sequence_type', 'first');
        $mandateId = (string) $request->input('mandate_id', 'mdt_e2e_mock');

        if ($subscriptionId <= 0) {
            return;
        }

        /** @var Subscription|null $subscription */
        $subscription = Subscription::find($subscriptionId);
        if (! $subscription) {
            return;
        }

        $user = $subscription->user;

        if ($status === 'paid') {
            $nextPaymentAt = now()->add(
                $subscription->billing_cycle === 'annual' ? '12 months' : '1 month'
            );

            $subscription->update([
                'status' => 'active',
                'mollie_mandate_id' => $mandateId,
                'next_payment_at' => $nextPaymentAt,
            ]);

            $user->update([
                'plan' => $subscription->plan,
                'plan_expires_at' => null,
            ]);

            Log::info('Mollie E2E mock: subscription activated', [
                'subscription_id' => $subscription->id,
                'sequence_type' => $sequenceType,
            ]);

            return;
        }

        if (in_array($status, ['failed', 'canceled', 'expired'], true) && $subscription->status === 'pending') {
            $subscription->update(['status' => 'cancelled']);
        }
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
        Payment $payment,
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

        // Per i pagamenti ricorrenti (rinnovo), aggiorna la data del prossimo pagamento
        if ($payment->sequenceType === 'recurring') {
            $nextPaymentAt = now()->add(
                $subscription->billing_cycle === 'annual' ? '12 months' : '1 month'
            );
            $subscription->update(['next_payment_at' => $nextPaymentAt]);
        }

        // Attiva il piano Pro sull'utente e rimuove l'eventuale data di scadenza
        // (es. se l'utente aveva cancellato e poi ha rinnovato manualmente)
        $user->update([
            'plan' => $subscription->plan,
            'plan_expires_at' => null,
        ]);

        // Aggiorna la subscription come attiva
        $subscription->update(['status' => 'active']);

        Log::info('Mollie: subscription activated', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'plan' => $subscription->plan,
            'sequence_type' => $payment->sequenceType,
        ]);
    }

    private function handleFailedPayment(
        Payment $payment,
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
        Payment $payment,
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
