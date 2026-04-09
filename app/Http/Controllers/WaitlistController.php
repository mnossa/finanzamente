<?php

namespace App\Http\Controllers;

use App\Services\WaitlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
}
