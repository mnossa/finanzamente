<?php

namespace App\Http\Controllers;

use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * TelegramLinkController
 *
 * Gestisce il collegamento tra account Telegram e account WebApp.
 * L'utente genera un token univoco dalla WebApp e lo usa nel bot Telegram.
 */
class TelegramLinkController extends Controller
{
    /**
     * Mostra la pagina di collegamento con il token corrente (se attivo) o la
     * possibilità di generarne uno nuovo.
     */
    public function show(): Response
    {
        $user = Auth::user();
        $activeToken = TelegramLinkToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        return Inertia::render('Telegram/Link', [
            'linked' => $user->telegram_chat_id !== null,
            'token' => $activeToken?->token,
            'tokenExpiresAt' => $activeToken?->expires_at?->toISOString(),
            'botUsername' => config('services.telegram.bot_username'),
        ]);
    }

    /**
     * Genera un nuovo token di collegamento per l'utente autenticato.
     * Il token ha validità 30 minuti.
     */
    public function generate(Request $request)
    {
        $user = Auth::user();

        // Invalida token precedenti non ancora usati
        TelegramLinkToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $token = TelegramLinkToken::create([
            'user_id' => $user->id,
            'token' => Str::random(32),
            'expires_at' => now()->addMinutes(30),
        ]);

        return back()->with('success', 'Nuovo token generato. Hai 30 minuti per usarlo nel bot Telegram.');
    }

    /**
     * Scollega l'account Telegram dall'utente autenticato.
     */
    public function unlink(Request $request)
    {
        $user = Auth::user();
        $user->update(['telegram_chat_id' => null]);

        return back()->with('success', 'Account Telegram scollegato con successo.');
    }
}
