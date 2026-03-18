<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\InboxItem;
use App\Models\TelegramLinkToken;
use App\Models\Transaction;
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
 *
 * Sintassi testo avanzata:
 *   "15 Pizza"                          → uscita €15, descrizione "Pizza"
 *   "+1500 Stipendio"                   → entrata €1500
 *   "15 Pizza @Corrente"               → uscita €15 con conto "Corrente"
 *   "15 Pizza #Alimentari"             → uscita €15 con categoria "Alimentari"
 *   "15 Pizza @Corrente #Alimentari"   → uscita €15 con conto e categoria
 *   "15 Pizza 01/03"                   → uscita €15 con data 01/03/anno corrente
 *   "+50 Rimborso @Corrente 15/03"     → entrata €50 con conto e data
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

        // Gestione comando /aiuto o /help
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

        // Comandi autenticati
        if (isset($message['text'])) {
            $text = trim($message['text']);

            if (str_starts_with($text, '/saldo')) {
                $this->handleSaldoCommand($user, $chatId);

                return response('OK', 200);
            }

            if (str_starts_with($text, '/ultime')) {
                $this->handleUltimeCommand($user, $chatId);

                return response('OK', 200);
            }

            if (str_starts_with($text, '/lista')) {
                $this->handleListaCommand($user, $chatId);

                return response('OK', 200);
            }
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
            "✅ <b>Account collegato con successo!</b>\n\nOra puoi inviare:\n• <code>15.50 Supermercato</code> → uscita\n• <code>+1500 Stipendio</code> → entrata\n• Foto di uno scontrino → OCR automatico\n\n🔧 <b>Opzionale:</b> aggiungi <code>@Conto</code>, <code>#Categoria</code> o data <code>DD/MM</code>\n\n💡 Usa /aiuto per la guida completa."
        );
    }

    /**
     * Invia la guida comandi aggiornata.
     */
    private function handleAiutoCommand(string $chatId): void
    {
        $inboxUrl = config('app.url') . '/inbox';

        $this->telegram->sendMessage(
            $chatId,
            "📖 <b>Guida Finanzamente Bot</b>\n\n"
            . "<b>📤 Registrare un'uscita:</b>\n"
            . "<code>15.50 Supermercato</code>\n"
            . "<code>Supermercato 15,50</code>\n"
            . "<code>15.50</code> (solo importo)\n\n"
            . "<b>📥 Registrare un'entrata:</b>\n"
            . "<code>+1500 Stipendio</code>\n"
            . "<code>+500</code> (solo importo)\n\n"
            . "<b>🔧 Dettagli opzionali (aggiungili al messaggio):</b>\n"
            . "<code>@NomeConto</code> → specifica il conto (es. <code>@Corrente</code>)\n"
            . "<code>#Categoria</code> → specifica la categoria (es. <code>#Alimentari</code>)\n"
            . "<code>DD/MM</code> → specifica la data (es. <code>01/03</code>)\n\n"
            . "<b>Esempi completi:</b>\n"
            . "<code>15 Pizza @Corrente #Cibo</code>\n"
            . "<code>+500 Rimborso @Corrente 15/03</code>\n"
            . "<code>8.50 Bar #Svago 01/03</code>\n\n"
            . "<b>📸 Scontrino fotografato:</b>\n"
            . "Invia direttamente la foto — l'OCR estrae importo e negozio automaticamente.\n\n"
            . "<b>⌨️ Comandi:</b>\n"
            . "/start TOKEN — collega il tuo account\n"
            . "/aiuto — questa guida\n"
            . "/saldo — mostra i saldi dei tuoi conti\n"
            . "/ultime — mostra le ultime 5 transazioni\n"
            . "/lista — mostra conti e categorie disponibili\n\n"
            . "🔍 <a href=\"{$inboxUrl}\">Vai all'Inbox</a> per revisionare le voci."
        );
    }

    /**
     * Mostra i saldi dei conti dell'utente.
     */
    private function handleSaldoCommand(User $user, string $chatId): void
    {
        $accounts = Account::where('household_id', $user->active_household_id)
            ->withSum('transactions', 'amount')
            ->get();

        if ($accounts->isEmpty()) {
            $this->telegram->sendMessage($chatId, '⚠️ Nessun conto disponibile. Crea un conto su Finanzamente.');

            return;
        }

        $lines = ["💰 <b>Saldi conti:</b>\n"];
        foreach ($accounts as $account) {
            $balance = (float) ($account->transactions_sum_amount ?? 0);
            $sign = $balance >= 0 ? '+' : '';
            $lines[] = "• <b>{$account->name}</b>: {$sign}" . number_format($balance, 2, ',', '.') . " {$account->currency_code}";
        }

        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    /**
     * Mostra le ultime 5 transazioni dell'utente.
     */
    private function handleUltimeCommand(User $user, string $chatId): void
    {
        $transactions = Transaction::where('user_id', $user->id)
            ->with(['account:id,name', 'category:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        if ($transactions->isEmpty()) {
            $this->telegram->sendMessage($chatId, '📭 Nessuna transazione registrata.');

            return;
        }

        $lines = ["📋 <b>Ultime 5 transazioni:</b>\n"];
        foreach ($transactions as $tx) {
            $amount = (float) $tx->amount;
            $sign = $amount >= 0 ? '+' : '';
            $emoji = $amount >= 0 ? '📈' : '💸';
            $date = $tx->date ? \Carbon\Carbon::parse($tx->date)->format('d/m') : '';
            $desc = $tx->description ?? ($tx->category?->name ?? '—');
            $lines[] = "{$emoji} {$date} <b>{$sign}" . number_format(abs($amount), 2, ',', '.') . " €</b> – {$desc}";
        }

        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    /**
     * Mostra i conti e le categorie disponibili dell'utente.
     */
    private function handleListaCommand(User $user, string $chatId): void
    {
        $accounts = Account::where('household_id', $user->active_household_id)
            ->orderBy('name')
            ->get();

        $categories = Category::where('household_id', $user->active_household_id)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $lines = ["📂 <b>Dati disponibili:</b>\n"];

        if ($accounts->isNotEmpty()) {
            $lines[] = "<b>🏦 Conti</b> (usa <code>@NomeConto</code>):";
            foreach ($accounts as $account) {
                $lines[] = "  • {$account->name}";
            }
        }

        $lines[] = '';

        if ($categories->isNotEmpty()) {
            $lines[] = "<b>🏷️ Categorie</b> (usa <code>#NomeCategoria</code>):";
            $expenseCategories = $categories->where('type', 'expense');
            $incomeCategories = $categories->where('type', 'income');

            if ($expenseCategories->isNotEmpty()) {
                $lines[] = "  <i>Uscite:</i> " . $expenseCategories->pluck('name')->join(', ');
            }
            if ($incomeCategories->isNotEmpty()) {
                $lines[] = "  <i>Entrate:</i> " . $incomeCategories->pluck('name')->join(', ');
            }
        }

        $this->telegram->sendMessage($chatId, implode("\n", $lines));
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

        // Parsing avanzato: importo, descrizione, tipo, conto, categoria, data
        $parsed = $this->parseTextMessage($text);
        $amount = $parsed['amount'];
        $description = $parsed['description'];
        $type = $parsed['type'];
        $date = $parsed['date'];

        // Risolvi conto e categoria per nome
        [$accountId, $resolvedAccount] = $this->resolveAccountByName($parsed['account_name'], $user);
        [$categoryId, $resolvedCategory] = $this->resolveCategoryByName($parsed['category_name'], $user);

        $item = InboxItem::create([
            'user_id' => $user->id,
            'household_id' => $user->active_household_id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'type' => $type,
            'raw_text' => $text,
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $date,
            'account_id' => $accountId,
            'category_id' => $categoryId,
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

            $extras = [];
            if ($accountId && $resolvedAccount) {
                $extras[] = "🏦 {$resolvedAccount->name}";
            } elseif ($parsed['account_name'] !== null) {
                $extras[] = "⚠️ Conto \"{$parsed['account_name']}\" non trovato";
            }
            if ($categoryId && $resolvedCategory) {
                $extras[] = "🏷️ {$resolvedCategory->name}";
            } elseif ($parsed['category_name'] !== null) {
                $extras[] = "⚠️ Categoria \"{$parsed['category_name']}\" non trovata";
            }
            if ($date && $date !== now()->toDateString()) {
                $extras[] = "📅 " . \Carbon\Carbon::parse($date)->format('d/m/Y');
            }

            $extrasText = ! empty($extras) ? "\n" . implode(' · ', $extras) : '';

            $this->telegram->sendMessage(
                $chatId,
                "✅ <b>Ricevuto!</b>\n\n{$preview}{$extrasText}\n\n🔍 <a href=\"" . config('app.url') . "/inbox\">Vai all'Inbox</a> per revisionare e confermare."
            );
        } else {
            $this->telegram->sendMessage(
                $chatId,
                "✅ <b>Ricevuto!</b>\n\n📝 <i>{$text}</i>\n\n⚠️ Nessun importo rilevato. <a href=\"" . config('app.url') . "/inbox\">Vai all'Inbox</a> per completare i dati."
            );
        }
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

        // Prova a estrarre dettagli opzionali dalla didascalia
        $caption = trim($message['caption'] ?? '');
        $captionParsed = $caption ? $this->parseTextMessage($caption) : null;

        $accountId = null;
        $categoryId = null;
        if ($captionParsed) {
            [$accountId] = $this->resolveAccountByName($captionParsed['account_name'], $user);
            [$categoryId] = $this->resolveCategoryByName($captionParsed['category_name'], $user);
        }

        // Crea l'elemento in Inbox
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
            'account_id' => $accountId,
            'category_id' => $categoryId,
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
                "✅ <b>Foto salvata!</b>\n\n⚠️ Non sono riuscito a estrarre tutti i dati. <a href=\"" . config('app.url') . "/inbox\">Vai all'Inbox</a> per inserire manualmente l'importo.\n\n💡 <i>Suggerimento: puoi aggiungere <code>@Conto #Categoria</code> come didascalia della foto.</i>"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    /**
     * Risolve un conto per nome nell'household dell'utente.
     * Restituisce [id|null, Account|null].
     *
     * @return array{int|null, \App\Models\Account|null}
     */
    private function resolveAccountByName(?string $name, User $user): array
    {
        if ($name === null) {
            return [null, null];
        }
        $account = Account::where('household_id', $user->active_household_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        return [$account?->id, $account];
    }

    /**
     * Risolve una categoria per nome nell'household dell'utente.
     * Restituisce [id|null, Category|null].
     *
     * @return array{int|null, \App\Models\Category|null}
     */
    private function resolveCategoryByName(?string $name, User $user): array
    {
        if ($name === null) {
            return [null, null];
        }
        $category = Category::where('household_id', $user->active_household_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        return [$category?->id, $category];
    }

    /**
     * Parsing avanzato di un messaggio testuale.
     * Estrae importo, descrizione, tipo (income/expense), conto (@), categoria (#) e data (DD/MM).
     *
     * Formato base:
     *   "15 Pizza"                       → uscita €15, desc "Pizza"
     *   "Pizza 8,50"                     → uscita €8.50, desc "Pizza"
     *   "+1500 Stipendio"                → entrata €1500, desc "Stipendio"
     *   "+500"                           → entrata €500
     *
     * Dettagli opzionali (in qualsiasi posizione dopo il testo base):
     *   "@Corrente"                      → conto "Corrente"
     *   "#Alimentari"                    → categoria "Alimentari"
     *   "01/03" o "01/03/2026"          → data
     *
     * @return array{amount: float|null, description: string|null, type: string, account_name: string|null, category_name: string|null, date: string}
     */
    private function parseTextMessage(string $text): array
    {
        $type = 'expense';
        $account_name = null;
        $category_name = null;

        // Prefisso + → entrata
        if (str_starts_with($text, '+')) {
            $type = 'income';
            $text = ltrim(substr($text, 1));
        }

        // Estrai @conto (es. @Corrente, @Banca Intesa)
        if (preg_match('/@([^\s#@]+(?:\s+[^\s#@]+)*)/u', $text, $m)) {
            $account_name = trim($m[1]);
            $text = trim(str_replace($m[0], '', $text));
        }

        // Estrai #categoria (es. #Alimentari, #Spesa Casa)
        if (preg_match('/#([^\s#@]+(?:\s+[^\s#@]+)*)/u', $text, $m)) {
            $category_name = trim($m[1]);
            $text = trim(str_replace($m[0], '', $text));
        }

        // Estrai data in formato DD/MM o DD/MM/YYYY (es. 01/03 o 01/03/2026)
        $date = now()->toDateString();
        if (preg_match('/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{4}))?\b/', $text, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = isset($m[3]) && $m[3] ? (int) $m[3] : now()->year;
            try {
                $parsedDate = \Carbon\Carbon::createFromDate($year, $month, $day);
                $date = $parsedDate->toDateString();
                $text = trim(preg_replace('/\b\d{1,2}\/\d{1,2}(?:\/\d{4})?\b/', '', $text));
            } catch (\Throwable) {
                // data non valida, usa oggi
            }
        }

        $text = trim($text);

        // Pattern: numero (opzionale decimale con . o ,) seguito da testo
        if (preg_match('/^(\d+(?:[.,]\d{1,2})?)\s+(.+)$/u', $text, $matches)) {
            $amount = (float) str_replace(',', '.', $matches[1]);
            $description = trim($matches[2]);

            return compact('amount', 'description', 'type', 'account_name', 'category_name', 'date');
        }

        // Pattern: testo seguito da numero
        if (preg_match('/^(.+)\s+(\d+(?:[.,]\d{1,2})?)$/u', $text, $matches)) {
            $amount = (float) str_replace(',', '.', $matches[2]);
            $description = trim($matches[1]);
            $description = $description !== '' ? $description : null;

            return compact('amount', 'description', 'type', 'account_name', 'category_name', 'date');
        }

        // Solo numero
        if (preg_match('/^(\d+(?:[.,]\d{1,2})?)$/', $text, $matches)) {
            $amount = (float) str_replace(',', '.', $matches[1]);
            $description = null;

            return compact('amount', 'description', 'type', 'account_name', 'category_name', 'date');
        }

        // Nessun importo trovato
        $amount = null;
        $description = $text !== '' ? $text : null;

        return compact('amount', 'description', 'type', 'account_name', 'category_name', 'date');
    }
}
