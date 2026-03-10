<?php

namespace App\Http\Controllers;

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
                "👋 <b>Benvenuto su Finanzamente!</b>\n\nPer collegare il tuo account, genera un token nella WebApp e invialo con:\n<code>/start IL_TUO_TOKEN</code>"
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
            "✅ <b>Account collegato con successo!</b>\n\nOra puoi inviare:\n• Testo (es. <i>15.50 Supermercato</i>)\n• Foto di uno scontrino\n\nLe spese appariranno nella tua Inbox su Finanzamente."
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

        // Parsing basico: prova a estrarre importo e descrizione
        ['amount' => $amount, 'description' => $description] = $this->parseTextMessage($text);

        $item = InboxItem::create([
            'user_id' => $user->id,
            'household_id' => $user->active_household_id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => $text,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => now()->toDateString(),
        ]);

        $preview = $amount !== null
            ? "💶 <b>€" . number_format((float) $amount, 2, ',', '.') . "</b>" . ($description ? " – {$description}" : '')
            : "📝 <i>{$text}</i>";

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
     * Prova a estrarre un importo numerico e una descrizione.
     * Esempi: "15 Pizza", "15.50 Supermercato Conad", "Pizza 8,50"
     *
     * @return array{amount: float|null, description: string|null}
     */
    private function parseTextMessage(string $text): array
    {
        // Pattern: numero (opzionale decimale con . o ,) seguito da testo
        if (preg_match('/^(\d+(?:[.,]\d{1,2})?)\s+(.+)$/u', $text, $matches)) {
            $amount = (float) str_replace(',', '.', $matches[1]);
            $description = trim($matches[2]);

            return ['amount' => $amount, 'description' => $description];
        }

        // Pattern: testo seguito da numero
        if (preg_match('/^(.+)\s+(\d+(?:[.,]\d{1,2})?)$/u', $text, $matches)) {
            $amount = (float) str_replace(',', '.', $matches[2]);
            $description = trim($matches[1]);
            $description = $description !== '' ? $description : null;

            return ['amount' => $amount, 'description' => $description];
        }

        // Solo numero
        if (preg_match('/^(\d+(?:[.,]\d{1,2})?)$/', trim($text), $matches)) {
            return ['amount' => (float) str_replace(',', '.', $matches[1]), 'description' => null];
        }

        return ['amount' => null, 'description' => $text !== '' ? $text : null];
    }
}
