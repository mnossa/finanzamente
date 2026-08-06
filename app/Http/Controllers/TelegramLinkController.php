<?php

namespace App\Http\Controllers;

use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Support\TelegramBotLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $activeToken = $this->findActiveToken($user);

        return Inertia::render('Telegram/Link', $this->linkPageProps($user, $activeToken));
    }

    /**
     * Genera un nuovo token di collegamento per l'utente autenticato.
     * Il token ha validità 30 minuti.
     */
    public function generate(Request $request): RedirectResponse
    {
        $user = Auth::user();

        TelegramLinkToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        TelegramLinkToken::create([
            'user_id' => $user->id,
            'token' => TelegramBotLink::generateStartPayload(),
            'expires_at' => now()->addMinutes(30),
        ]);

        return redirect()
            ->route('telegram.link.show')
            ->with('success', 'Nuovo token generato. Hai 30 minuti per usarlo nel bot Telegram.');
    }

    /**
     * Scollega l'account Telegram dall'utente autenticato.
     */
    public function unlink(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $user->update(['telegram_chat_id' => null]);

        return redirect()
            ->route('telegram.link.show')
            ->with('success', 'Account Telegram scollegato con successo.');
    }

    private function findActiveToken(User $user): ?TelegramLinkToken
    {
        return TelegramLinkToken::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * @return array{linked: bool, token: string|null, tokenExpiresAt: string|null, botUsername: string|null, botDeepLink: string|null}
     */
    private function linkPageProps(User $user, ?TelegramLinkToken $activeToken): array
    {
        $botUsername = TelegramBotLink::normalizeBotUsername(config('services.telegram.bot_username'));
        $token = $activeToken?->token;

        return [
            'linked' => $user->telegram_chat_id !== null,
            'token' => $token,
            'tokenExpiresAt' => $activeToken?->expires_at?->toISOString(),
            'botUsername' => $botUsername,
            'botDeepLink' => TelegramBotLink::buildDeepLink($botUsername, $token),
        ];
    }
}
