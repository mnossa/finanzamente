<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\MollieService;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly PlanService $planService,
        private readonly MollieService $mollieService,
    ) {}

    /**
     * Avvia il flusso di acquisto del piano Pro.
     * Crea una Subscription pending e redirige al checkout Mollie.
     */
    public function checkout(Request $request): RedirectResponse
    {
        if (! $this->planService->isProEnabled()) {
            return redirect()->route('profile.edit')
                ->with('error', 'L\'acquisto del piano Pro non è attualmente disponibile.');
        }

        $request->validate([
            'billing_cycle' => 'required|in:monthly,annual',
        ]);

        $user = Auth::user();
        $billingCycle = $request->billing_cycle;
        $priceCents = $this->planService->getPriceCents('pro', $billingCycle);

        // Crea la subscription in stato pending
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => $billingCycle,
            'status' => 'pending',
            'currency' => 'EUR',
            'amount_cents' => $priceCents,
            'billing_name' => $request->billing_name ?? $user->name,
            'billing_email' => $request->billing_email ?? $user->email,
            'billing_address' => $request->billing_address,
            'billing_city' => $request->billing_city,
            'billing_zip' => $request->billing_zip,
            'billing_country' => $request->billing_country ?? 'IT',
            'billing_vat' => $request->billing_vat ?? $user->vat_number,
            'billing_company' => $request->billing_company,
        ]);

        $redirectUrl = route('subscription.return', ['subscription' => $subscription->id]);
        $webhookUrl = route('mollie.webhook');

        try {
            $checkoutUrl = $this->mollieService->createFirstPaymentUrl(
                $user,
                $subscription,
                $redirectUrl,
                $webhookUrl
            );

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            $subscription->delete();
            report($e);

            return redirect()->route('profile.edit')
                ->with('error', 'Impossibile avviare il pagamento. Riprova più tardi.');
        }
    }

    /**
     * URL di ritorno da Mollie dopo il checkout.
     * Verifica lo stato e mostra il risultato all'utente.
     */
    public function return(Request $request, Subscription $subscription): RedirectResponse
    {
        // Verifica che la subscription appartenga all'utente autenticato
        if ($subscription->user_id !== Auth::id()) {
            abort(403);
        }

        // Lo stato viene aggiornato via webhook; qui mostriamo solo un messaggio
        if ($subscription->status === 'active') {
            return redirect()->route('profile.subscription')
                ->with('success', 'Abbonamento Pro attivato con successo! Benvenuto.');
        }

        if ($subscription->status === 'pending') {
            return redirect()->route('profile.subscription')
                ->with('info', 'Il pagamento è in elaborazione. Riceverai una conferma via email.');
        }

        return redirect()->route('profile.subscription')
            ->with('error', 'Il pagamento non è andato a buon fine. Nessun addebito è stato effettuato.');
    }

    /**
     * Disabilita il rinnovo automatico dell'abbonamento attivo.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $subscription = $user->activeSubscription();

        if (! $subscription || ! $subscription->isActive()) {
            return redirect()->route('profile.subscription')
                ->with('error', 'Nessun abbonamento attivo da cancellare.');
        }

        try {
            $this->mollieService->cancelSubscription($subscription);
            $user->update(['plan' => 'base']);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('profile.subscription')
                ->with('error', 'Impossibile cancellare l\'abbonamento. Riprova o contatta il supporto.');
        }

        return redirect()->route('profile.subscription')
            ->with('success', 'Rinnovo automatico disabilitato. L\'abbonamento rimarrà attivo fino alla scadenza.');
    }

    /**
     * Avvia il flusso per aggiornare il metodo di pagamento.
     */
    public function updatePaymentMethod(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $subscription = $user->activeSubscription();

        if (! $subscription) {
            return redirect()->route('profile.subscription')
                ->with('error', 'Nessun abbonamento attivo.');
        }

        $redirectUrl = route('subscription.payment-method.return', ['subscription' => $subscription->id]);
        $webhookUrl = route('mollie.webhook');

        try {
            $checkoutUrl = $this->mollieService->createUpdatePaymentMethodUrl(
                $user,
                $subscription,
                $redirectUrl,
                $webhookUrl
            );

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('profile.subscription')
                ->with('error', 'Impossibile aprire la pagina di modifica metodo di pagamento.');
        }
    }

    /**
     * URL di ritorno dopo aggiornamento metodo di pagamento.
     */
    public function paymentMethodReturn(Request $request, Subscription $subscription): RedirectResponse
    {
        if ($subscription->user_id !== Auth::id()) {
            abort(403);
        }

        return redirect()->route('profile.subscription')
            ->with('info', 'Richiesta di aggiornamento metodo di pagamento inviata. Verrà applicata al prossimo rinnovo.');
    }

    /**
     * Aggiorna i dati di fatturazione dell'abbonamento.
     */
    public function updateBilling(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $subscription = $user->activeSubscription();

        if (! $subscription) {
            return redirect()->route('profile.subscription')
                ->with('error', 'Nessun abbonamento attivo.');
        }

        $validated = $request->validate([
            'billing_name' => 'required|string|max:255',
            'billing_email' => 'required|email|max:255',
            'billing_address' => 'nullable|string|max:500',
            'billing_city' => 'nullable|string|max:100',
            'billing_zip' => 'nullable|string|max:20',
            'billing_country' => 'nullable|string|size:2',
            'billing_vat' => 'nullable|string|max:50',
            'billing_company' => 'nullable|string|max:255',
        ]);

        $subscription->update($validated);

        return redirect()->route('profile.subscription')
            ->with('success', 'Dati di fatturazione aggiornati.');
    }

    /**
     * Pagina abbonamento nell'area profilo.
     */
    public function show(): Response
    {
        $user = Auth::user();
        $subscription = $user->activeSubscription();
        $plans = $this->planService->getPlansForFrontend();

        return Inertia::render('Profile/Subscription', [
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'plan' => $subscription->plan,
                'billing_cycle' => $subscription->billing_cycle,
                'status' => $subscription->status,
                'amount_cents' => $subscription->amount_cents,
                'formatted_amount' => $subscription->formatted_amount,
                'currency' => $subscription->currency,
                'next_payment_at' => $subscription->next_payment_at?->format('d/m/Y'),
                'ends_at' => $subscription->ends_at?->format('d/m/Y'),
                'billing_name' => $subscription->billing_name,
                'billing_email' => $subscription->billing_email,
                'billing_address' => $subscription->billing_address,
                'billing_city' => $subscription->billing_city,
                'billing_zip' => $subscription->billing_zip,
                'billing_country' => $subscription->billing_country,
                'billing_vat' => $subscription->billing_vat,
                'billing_company' => $subscription->billing_company,
            ] : null,
            'currentPlan' => $user->plan,
            'plans' => $plans,
            'proEnabled' => $this->planService->isProEnabled(),
        ]);
    }
}
