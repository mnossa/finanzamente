<?php

namespace App\Http\Controllers;

use App\Services\WaitlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller per la gestione della waitlist Pro (pre-lancio).
 * Accetta le iscrizioni via form pubblico, invia la richiesta a Brevo
 * con double opt-in e salva la firma HMAC sull'attributo SIGNATURE.
 */
class WaitlistController extends Controller
{
    public function __construct(private readonly WaitlistService $waitlistService) {}

    /**
     * Gestisce l'iscrizione alla waitlist Pro.
     * Rate limited: max 3 tentativi ogni 5 minuti per IP (adv-throttle).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|string|email|max:255',
        ]);

        $email = strtolower(trim($request->input('email')));

        $this->waitlistService->subscribe($email);

        // Risposta generica per non rivelare se l'email era già presente
        return back()->with('waitlist_success', true);
    }

    /**
     * Webhook Tally → Brevo.
     *
     * Tally invia un POST con payload JSON ogni volta che qualcuno completa
     * il sondaggio. Se il form contiene un campo email, viene iscritto
     * automaticamente alla waitlist su Brevo con double opt-in.
     *
     * Sicurezza: la firma HMAC-SHA256 nell'header X-Tally-Signature
     * viene verificata usando TALLY_WEBHOOK_SECRET in .env.
     * Senza secret configurato, il webhook è disabilitato.
     *
     * Configurazione su Tally:
     *   Integrations → Webhooks → URL: https://tuodominio.it/webhooks/tally
     */
    public function tallyWebhook(Request $request): JsonResponse
    {
        $secret = config('services.tally.webhook_secret');

        if (empty($secret)) {
            return response()->json(['ok' => false, 'error' => 'webhook not configured'], 501);
        }

        // Verifica firma Tally (HMAC-SHA256 del raw body)
        // Tally invia 'X-Tally-Signature' (form embed) o 'Tally-Signature' (survey)
        $signature = $request->header('X-Tally-Signature')
            ?? $request->header('Tally-Signature', '');
        $rawBody = $request->getContent();
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        if (! hash_equals($expected, $signature)) {
            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        // Estrae l'email dal payload Tally
        // Struttura attesa: { "data": { "fields": [ { "type": "INPUT_EMAIL", "value": "..." } ] } }
        $fields = $request->json('data.fields', []);
        $email = null;

        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'INPUT_EMAIL' && ! empty($field['value'])) {
                $email = strtolower(trim($field['value']));
                break;
            }
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Sondaggio senza campo email (o email non valida): ok silenzioso
            return response()->json(['ok' => true, 'subscribed' => false]);
        }

        try {
            $this->waitlistService->subscribe($email);
        } catch (\Throwable $e) {
            Log::error('Brevo waitlist webhook: eccezione non gestita.', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true, 'subscribed' => true]);
    }

    /**
     * Conferma l'iscrizione alla waitlist tramite link firmato ricevuto via email.
     * Aggiunge il contatto alla lista Brevo e reindirizza alla pagina di conferma.
     */
    public function confirm(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link di conferma non valido o scaduto.');
        }

        $email = strtolower(trim($request->query('email', '')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            abort(400);
        }

        $this->waitlistService->confirmSubscription($email);

        return redirect()->route('waitlist.confirmed');
    }
}
