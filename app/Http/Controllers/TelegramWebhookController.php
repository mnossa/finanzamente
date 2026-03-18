<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\InboxItem;
use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\VisionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * TelegramWebhookController
 *
 * Riceve e processa i messaggi in arrivo dal bot Telegram.
 * Supporta messaggi testuali e foto (scontrini).
 *
 * Flusso:
 * 1. Riceve l'update dal webhook di Telegram
 * 2. Identifica l'utente tramite telegram_chat_id
 * 3. Crea un InboxItem in stato draft/needs_review
 * 4. Invia feedback di ricezione all'utente Telegram
 */
class TelegramWebhookController extends Controller
{
    public function __construct(
        private TelegramService $telegram,
        private VisionService $vision,
    ) {}

    /**
     * Endpoint pubblico per il webhook di Telegram.
     * Deve rispondere 200 rapidamente; l'elaborazione pesante andrebbe in coda.
     */
    public function handle(Request $request): Response
    {
        $update = $request->all();

        // Telegram invia solo aggiornamenti di tipo 'message'
        if (! isset($update['message'])) {
            return response('OK', 200);
        }

        $message = $update['message'];
        $chatId = (string) ($message['chat']['id'] ?? '');

        if (! $chatId) {
            return response('OK', 200);
        }

        // Gestione comando /start con token di collegamento
        if (isset($message['text']) && str_starts_with($message['text'], '/start')) {
            $this->handleStartCommand($message, $chatId);

            return response('OK', 200);
        }

        // Gestione comando /aiuto
        if (isset($message['text']) && (
            str_starts_with($message['text'], '/aiuto')
            || str_starts_with($message['text'], '/help')
        )) {
            $this->handleAiutoCommand($chatId);

            return response('OK', 200);
        }

        // Ricerca utente collegato a questo chatId
        $user = User::where('telegram_chat_id', $chatId)->first();

        if (! $user) {
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ <b>Account non collegato</b>\n\nPer iniziare, collega il tuo account su <b>Finanzamente</b> e usa il token generato con il comando /start."
            );

            return response('OK', 200);
        }

        // Determina il tipo di messaggio e processa
        if (isset($message['photo'])) {
            $this->handlePhotoMessage($message, $user, $chatId);
        } elseif (isset($message['text'])) {
            $this->handleTextMessage($message, $user, $chatId);
        } else {
            $this->telegram->sendMessage(
                $chatId,
                '⚠️ Tipo di messaggio non supportato. Invia del testo (es. <i>15 Pizza</i>) oppure la foto di uno scontrino.'
            );
        }

        return response('OK', 200);
    }

    // -------------------------------------------------------------------------
    // Gestione comandi
    // -------------------------------------------------------------------------

    private function handleStartCommand(array $message, string $chatId): void
    {
        $parts = explode(' ', $message['text'] ?? '', 2);
        $token = $parts[1] ?? null;

        if (! $token) {
            $this->telegram->sendMessage(
                $chatId,
                "👋 <b>Benvenuto su Finanzamente!</b>\n\nPer collegare il tuo account, genera un token nella WebApp e invialo con:\n<code>/start IL_TUO_TOKEN</code>\n\n💡 Usa /aiuto per vedere tutti i comandi."
            );

            return;
        }

        $linkToken = TelegramLinkToken::where('token', $token)->first();

        if (! $linkToken || ! $linkToken->isValid()) {
            $this->telegram->sendMessage(
                $chatId,
                '❌ <b>Token non valido o scaduto.</b>\n\nGenera un nuovo token dalla WebApp e riprova.'
            );

            return;
        }

        // Controlla che questo chatId non sia già collegato a un altro account
        $existingUser = User::where('telegram_chat_id', $chatId)->first();
        if ($existingUser && $existingUser->id !== $linkToken->user_id) {
            $this->telegram->sendMessage(
                $chatId,
                '⚠️ Questo account Telegram è già collegato a un altro profilo Finanzamente.'
            );

            return;
        }

        // Collega l'account
        $linkToken->user->update(['telegram_chat_id' => $chatId]);
        $linkToken->update(['used_at' => now()]);

        $this->telegram->sendMessage(
            $chatId,
            "✅ <b>Account collegato con successo!</b>\n\nOra puoi inviare:\n• Testo (es. <i>15.50 Supermercato</i>) → uscita\n• Testo con + (es. <i>+1500 Stipendio</i>) → entrata\n• Foto di uno scontrino → OCR automatico\n\n💡 Usa /aiuto per la guida completa."
        );
    }

    /**
     * Invia la guida comandi.
     */
    private function handleAiutoCommand(string $chatId): void
    {
        $inboxUrl = config('app.url') . '/inbox';

        $this->telegram->sendMessage(
            $chatId,
            "📖 <b>Guida Finanzamente Bot</b>\n\n"
            . "<b>Registrare un'uscita:</b>\n"
            . "<code>15.50 Supermercato</code>\n"
            . "<code>Supermercato 15,50</code>\n"
            . "<code>15.50</code> (solo importo)\n\n"
            . "<b>Registrare un'entrata:</b>\n"
            . "<code>+1500 Stipendio</code>\n"
            . "<code>+500</code> (solo importo)\n\n"
            . "<b>Scontrino fotografato:</b>\n"
            . "Invia direttamente la foto — l'OCR estrae importo e negozio automaticamente.\n\n"
            . "<b>Comandi:</b>\n"
            . "/start TOKEN — collega il tuo account\n"
            . "/aiuto — questa guida\n\n"
            . "🔍 <a href=\"{$inboxUrl}\">Vai all'Inbox</a> per revisionare le voci."
        );
    }

    // -------------------------------------------------------------------------
    // Gestione messaggi testuali
    // -------------------------------------------------------------------------

    private function handleTextMessage(array $message, User $user, string $chatId): void
    {
        $text = trim($message['text'] ?? '');

        if (empty($text)) {
            return;
        }

        // Parsing: prova a estrarre importo, descrizione e tipo (entrata/uscita)
        ['amount' => $amount, 'description' => $description, 'type' => $type] = $this->parseTextMessage($text);

        $item = InboxItem::create([
            'user_id' => $user->id,
            'household_id' => $user->active_household_id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'type' => $type,
            'raw_text' => $text,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => now()->toDateString(),
        ]);

        AppNotification::create([
            'user_id' => $user->id,
            'title' => $type === 'income' ? '📈 Nuova entrata in Inbox' : '💸 Nuova uscita in Inbox',
            'message' => $amount !== null
                ? 'Ricevuto da Telegram: ' . ($description ?? $text) . ' — €' . number_format((float) $amount, 2, ',', '.')
                : 'Messaggio Telegram salvato in Inbox: ' . mb_strimwidth($text, 0, 80, '…'),
            'notification_key' => 'inbox_telegram_' . $item->id,
        ]);

        if ($amount !== null) {
            $amountFormatted = '€' . number_format((float) $amount, 2, ',', '.');
            $typeEmoji = $type === 'income' ? '📈' : '💸';
            $typeLabel = $type === 'income' ? 'Entrata' : 'Uscita';
            $preview = "{$typeEmoji} <b>{$typeLabel}: {$amountFormatted}</b>" . ($description ? " – {$description}" : '');
        } else {
            $preview = "📝 <i>{$text}</i>";
        }

        $this->telegram->sendMessage(
            $chatId,
            "✅ <b>Ricevuto!</b>\n\n{$preview}\n\n🔍 <a href=\"" . config('app.url') . "/inbox\">Vai all'Inbox</a> per revisionare e confermare."
        );
    }

    // -------------------------------------------------------------------------
    // Gestione messaggi con foto
    // -------------------------------------------------------------------------

    private function handlePhotoMessage(array $message, User $user, string $chatId): void
    {
        // Prendi la foto con la risoluzione più alta (ultimo elemento dell'array)
        $photos = $message['photo'];
        $bestPhoto = end($photos);
        $fileId = $bestPhoto['file_id'] ?? null;

        if (! $fileId) {
            $this->telegram->sendMessage($chatId, '❌ Impossibile accedere alla foto. Riprova.');

            return;
        }

        // Scarica la foto
        $imageContent = $this->telegram->downloadFile($fileId);
        if (! $imageContent) {
            $this->telegram->sendMessage($chatId, '❌ Impossibile scaricare la foto. Riprova.');

            return;
        }

        // Salva l'immagine nel disco privato
        $filename = 'inbox/' . now()->format('Y-m-d_His') . '_' . Str::random(8) . '.jpg';
        Storage::disk('private')->put($filename, $imageContent);

        // Invia feedback immediato (l'OCR può richiedere qualche secondo)
        $this->telegram->sendMessage(
            $chatId,
            "📸 <b>Foto ricevuta!</b> Sto elaborando lo scontrino..."
        );

        // Estrazione OCR con Mistral Pixtral
        $aiPayload = null;
        $amount = null;
        $description = null;
        $status = 'draft';

        try {
            $aiPayload = $this->vision->extractFromReceipt($filename);

            if ($aiPayload) {
                $amount = $aiPayload['amt'] ?? null;
                $description = $aiPayload['shop'] ?? null;
                $status = 'needs_review';
            }
        } catch (\Throwable $e) {
            Log::warning('TelegramWebhookController: errore OCR', ['error' => $e->getMessage()]);
        }

        // Crea l'elemento in Inbox
        $caption = trim($message['caption'] ?? '');
        $item = InboxItem::create([
            'user_id' => $user->id,
            'household_id' => $user->active_household_id,
            'status' => $status,
            'source' => 'telegram_photo',
            'raw_text' => $caption ?: null,
            'image_path' => $filename,
            'ai_payload' => $aiPayload,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => isset($aiPayload['dt']) ? $aiPayload['dt'] : now()->toDateString(),
        ]);

        AppNotification::create([
            'user_id' => $user->id,
            'title' => '📸 Scontrino in Inbox',
            'message' => $aiPayload && $amount !== null
                ? 'Scontrino elaborato: ' . ($description ?? 'negozio sconosciuto') . ' — €' . number_format((float) $amount, 2, ',', '.')
                : 'Foto scontrino salvata in Inbox. Vai nell\'Inbox per completare i dati.',
            'notification_key' => 'inbox_telegram_' . $item->id,
        ]);

        // Feedback con risultato OCR
        if ($aiPayload && $amount !== null) {
            $dateFormatted = $item->transaction_date?->format('d/m/Y') ?? '';
            $preview = "💶 <b>€" . number_format((float) $amount, 2, ',', '.') . "</b>"
                . ($description ? " – {$description}" : '')
                . ($dateFormatted ? " ({$dateFormatted})" : '');

            $this->telegram->sendMessage(
                $chatId,
                "✅ <b>Scontrino elaborato!</b>\n\n{$preview}\n\n🔍 <a href=\"" . config('app.url') . "/inbox\">Vai all'Inbox</a> per verificare e confermare."
            );
        } else {
            $this->telegram->sendMessage(
                $chatId,
                "✅ <b>Foto salvata!</b>\n\n⚠️ Non sono riuscito a estrarre tutti i dati. <a href=\"" . config('app.url') . "/inbox\">Vai all'Inbox</a> per inserire manualmente l'importo."
            );
        }
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    /**
     * Parsing basilare di un messaggio testuale.
     * Prova a estrarre un importo numerico, la descrizione e il tipo (income/expense).
     *
     * Formato:
     *   "15 Pizza"           → uscita €15
     *   "Pizza 8,50"         → uscita €8.50
     *   "+1500 Stipendio"    → entrata €1500
     *   "+500"               → entrata €500
     *
     * @return array{amount: float|null, description: string|null, type: string}
     */
    private function parseTextMessage(string $text): array
    {
        $type = 'expense';

        // Prefisso + → entrata
        if (str_starts_with($text, '+')) {
            $type = 'income';
            $text = ltrim(substr($text, 1));
        }

        // Pattern: numero (opzionale decimale con . o ,) seguito da testo
        if (preg_match('/^(\d+(?:[.,]\d{1,2})?)\s+(.+)$/u', $text, $matches)) {
            $amount = (float) str_replace(',', '.', $matches[1]);
            $description = trim($matches[2]);

            return ['amount' => $amount, 'description' => $description, 'type' => $type];
        }

        // Pattern: testo seguito da numero
        if (preg_match('/^(.+)\s+(\d+(?:[.,]\d{1,2})?)$/u', $text, $matches)) {
            $amount = (float) str_replace(',', '.', $matches[2]);
            $description = trim($matches[1]);
            $description = $description !== '' ? $description : null;

            return ['amount' => $amount, 'description' => $description, 'type' => $type];
        }

        // Solo numero
        if (preg_match('/^(\d+(?:[.,]\d{1,2})?)$/', trim($text), $matches)) {
            return ['amount' => (float) str_replace(',', '.', $matches[1]), 'description' => null, 'type' => $type];
        }

        return ['amount' => null, 'description' => $text !== '' ? $text : null, 'type' => $type];
    }
}
