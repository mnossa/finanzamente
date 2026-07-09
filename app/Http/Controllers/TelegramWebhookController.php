<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\DebtCredit;
use App\Models\Household;
use App\Models\InboxItem;
use App\Models\RecurringTransaction;
use App\Models\TelegramLinkToken;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyConverter;
use App\Services\ModuleAccessService;
use App\Services\TelegramService;
use App\Services\VisionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

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
        private CurrencyConverter $currency,
        private ModuleAccessService $moduleAccess,
    ) {}

    /**
     * Mappa simboli valuta → codici ISO 4217 supportati nel parser bot.
     * `$` interpretato come USD (default plausibile italiano).
     */
    private const SYMBOL_TO_ISO = [
        '€' => 'EUR',
        '£' => 'GBP',
        '$' => 'USD',
        '¥' => 'JPY',
    ];

    /**
     * Endpoint pubblico per il webhook di Telegram.
     * Deve rispondere 200 rapidamente; l'elaborazione pesante andrebbe in coda.
     */
    public function handle(Request $request): Response
    {
        $webhookSecret = (string) config('services.telegram.webhook_secret', '');
        if ($webhookSecret !== '') {
            $receivedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (! hash_equals($webhookSecret, $receivedSecret)) {
                Log::warning('Telegram webhook rejected: secret token mismatch (rigenera webhook con make set-telegram-webhook)', [
                    'ip' => $request->ip(),
                    'has_header' => $receivedSecret !== '',
                ]);

                return response('Unauthorized', 401);
            }
        }

        $update = $request->all();
        $updateId = isset($update['update_id']) ? (int) $update['update_id'] : null;

        // Idempotenza: Telegram può reinviare lo stesso update se non riceve ACK.
        if ($updateId !== null) {
            $cacheKey = "telegram_webhook:update:{$updateId}";
            if (! Cache::add($cacheKey, now()->timestamp, now()->addHours(12))) {
                return response('OK', 200);
            }
        }

        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);

            return response('OK', 200);
        }

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

            if (str_starts_with($text, '/casa')) {
                $this->handleCasaCommand($user, $chatId);

                return response('OK', 200);
            }

            if (str_starts_with($text, '/debiti')) {
                $this->handleDebtsListCommand($user, $chatId, 'debt');

                return response('OK', 200);
            }

            if (str_starts_with($text, '/crediti')) {
                $this->handleDebtsListCommand($user, $chatId, 'credit');

                return response('OK', 200);
            }

            if (str_starts_with($text, '/debito')) {
                $this->handleCreateDebtCreditCommand($user, $chatId, 'debt', $text);

                return response('OK', 200);
            }

            if (str_starts_with($text, '/credito')) {
                $this->handleCreateDebtCreditCommand($user, $chatId, 'credit', $text);

                return response('OK', 200);
            }

            if (str_starts_with($text, '/ricorrenze')) {
                $this->handleRecurringListCommand($user, $chatId);

                return response('OK', 200);
            }

            if (str_starts_with($text, '/households')) {
                $this->handleHouseholdsOverviewCommand($user, $chatId);

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
                '⚠️ Tipo di messaggio non supportato. Invia del testo (es. <i>15 Pizza</i>).'
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
            "✅ <b>Account collegato con successo!</b>\n\nOra puoi inviare:\n• <code>15.50 Supermercato</code> → uscita\n• <code>+1500 Stipendio</code> → entrata\n\n🔧 <b>Opzionale:</b> aggiungi <code>@Conto</code>, <code>#Categoria</code> o data <code>DD/MM</code>\n\n💡 Usa /aiuto per la guida completa."
        );
    }

    /**
     * Invia la guida comandi aggiornata.
     */
    private function handleAiutoCommand(string $chatId): void
    {
        $inboxUrl = $this->inboxUrl();

        $this->telegram->sendMessage(
            $chatId,
            "📖 <b>Guida Finanzamente Bot</b>\n\n"
            ."<b>📤 Registrare un'uscita:</b>\n"
            ."<code>15.50 Supermercato</code>\n"
            ."<code>Supermercato 15,50</code>\n"
            ."<code>15.50</code> (solo importo)\n\n"
            ."<b>📥 Registrare un'entrata:</b>\n"
            ."<code>+1500 Stipendio</code>\n"
            ."<code>+500</code> (solo importo)\n\n"
            ."<b>🔧 Dettagli opzionali (aggiungili al messaggio):</b>\n"
            ."<code>@NomeConto</code> → specifica il conto (es. <code>@Corrente</code>)\n"
            ."<code>#Categoria</code> → specifica la categoria (es. <code>#Alimentari</code>)\n"
            ."<code>DD/MM</code> → specifica la data (es. <code>01/03</code>)\n\n"
            ."<b>💱 Valuta diversa da euro:</b>\n"
            ."<code>30 GBP cena pub</code> → 30 sterline (cambio automatico)\n"
            ."<code>£30 cena pub</code> → equivalente con simbolo\n"
            ."<code>50 USD hotel</code> oppure <code>$50 hotel</code>\n"
            ."<code>30 GBP cena ~1.18</code> → cambio fisso (1 GBP = 1.18 EUR)\n\n"
            ."<b>Esempi completi:</b>\n"
            ."<code>15 Pizza @Corrente #Cibo</code>\n"
            ."<code>+500 Rimborso @Corrente 15/03</code>\n"
            ."<code>8.50 Bar #Svago 01/03</code>\n"
            ."<code>£30 cena pub @Revolut #Svago</code>\n\n"
            ."<b>⌨️ Comandi:</b>\n"
            ."/start TOKEN — collega il tuo account\n"
            ."/aiuto — questa guida\n"
            ."/saldo — mostra i saldi dei tuoi conti\n"
            ."/ultime — mostra le ultime 5 transazioni\n"
            ."/lista — mostra conti e categorie disponibili\n"
            ."/casa — cambia nucleo familiare attivo\n"
            ."/debiti — elenco debiti aperti\n"
            ."/crediti — elenco crediti aperti\n"
            ."/debito 500 Mario Rossi — crea un debito\n"
            ."/credito 200 Cliente — crea un credito\n"
            ."/ricorrenze — mostra ricorrenze attive\n"
            ."/households — panoramica nuclei\n\n"
            ."🔍 <a href=\"{$inboxUrl}\">Vai all'Inbox</a> per revisionare le voci."
        );
    }

    private function handleCasaCommand(User $user, string $chatId): void
    {
        $households = $user->households()->orderBy('name')->get();

        if ($households->isEmpty()) {
            $this->telegram->sendMessage($chatId, '⚠️ Nessun nucleo disponibile sul tuo account.');

            return;
        }

        $activeId = $user->active_household_id;
        $lines = ["🏠 <b>I tuoi nuclei:</b>\n"];
        foreach ($households as $household) {
            $marker = $household->id === $activeId ? '✅ ' : '• ';
            $lines[] = "{$marker}<b>{$household->name}</b>";
        }
        $lines[] = "\nTocca un pulsante per attivare il nucleo:";

        $keyboard = $households->map(fn (Household $h) => [[
            'text' => ($h->id === $activeId ? '✅ ' : '').$h->name,
            'callback_data' => 'household:'.$h->id,
        ]])->values()->all();

        $this->telegram->sendMessage($chatId, implode("\n", $lines), 'HTML', [
            'inline_keyboard' => $keyboard,
        ]);
    }

    private function handleDebtsListCommand(User $user, string $chatId, string $type): void
    {
        if (! $this->moduleAccess->canAccessModuleById($user, 'debts_credits')) {
            $this->telegram->sendMessage($chatId, '⚠️ Il modulo Debiti/Crediti non è disponibile sul tuo piano.');

            return;
        }

        if (! $user->active_household_id) {
            $this->telegram->sendMessage($chatId, '⚠️ Seleziona prima un nucleo con /casa.');

            return;
        }

        $label = $type === 'debt' ? 'debiti' : 'crediti';
        $items = DebtCredit::where('household_id', $user->active_household_id)
            ->where('type', $type)
            ->whereIn('status', ['open', 'overdue'])
            ->orderByRaw("CASE status WHEN 'overdue' THEN 0 ELSE 1 END")
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        if ($items->isEmpty()) {
            $this->telegram->sendMessage($chatId, "📭 Nessun {$label} aperto.");

            return;
        }

        $lines = ['📋 <b>'.ucfirst($label).' aperti:</b>', ''];
        foreach ($items as $item) {
            $remaining = $item->getRemainingAmount();
            $formatted = $this->formatAmount($remaining, $item->currency_code);
            $due = $item->due_date ? ' — scad. '.$item->due_date->format('d/m/Y') : '';
            $lines[] = "• <b>{$item->counterparty}</b>: {$formatted}{$due}";
        }

        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    private function handleCreateDebtCreditCommand(User $user, string $chatId, string $type, string $text): void
    {
        if (! $this->moduleAccess->canAccessModuleById($user, 'debts_credits')) {
            $this->telegram->sendMessage($chatId, '⚠️ Il modulo Debiti/Crediti non è disponibile sul tuo piano.');

            return;
        }

        if (! $user->active_household_id) {
            $this->telegram->sendMessage($chatId, '⚠️ Seleziona prima un nucleo con /casa.');

            return;
        }

        if (! $this->userCanModifyHousehold($user)) {
            $this->telegram->sendMessage($chatId, '⚠️ Non hai permessi di modifica su questo nucleo.');

            return;
        }

        $rest = trim(substr($text, strpos($text, ' ') ?: strlen($text)));
        if (! preg_match('/^([\d.,]+)\s+(.+)$/u', $rest, $m)) {
            $example = $type === 'debt' ? '/debito 500 Mario Rossi' : '/credito 200 Cliente SPA';
            $this->telegram->sendMessage($chatId, "⚠️ Sintassi: <code>{$example}</code>");

            return;
        }

        $amount = (float) str_replace(',', '.', $m[1]);
        $counterparty = trim($m[2]);

        if ($amount < 0.01) {
            $this->telegram->sendMessage($chatId, '⚠️ Importo non valido.');

            return;
        }

        if (! $user->isPro()) {
            $max = config('plans.base_limits.max_debts_credits', 5);
            $count = DebtCredit::where('household_id', $user->active_household_id)
                ->whereIn('status', ['open', 'overdue'])
                ->count();
            if ($count >= $max) {
                $this->telegram->sendMessage($chatId, "⚠️ Limite di {$max} posizioni attive raggiunto (piano Base).");

                return;
            }
        }

        $debtCredit = DebtCredit::create([
            'household_id' => $user->active_household_id,
            'user_id' => $user->id,
            'counterparty' => $counterparty,
            'amount' => $amount,
            'initial_amount' => $amount,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'type' => $type,
            'status' => 'open',
        ]);

        $typeLabel = $type === 'debt' ? 'Debito' : 'Credito';
        $formatted = $this->formatAmount($amount, 'EUR');
        $this->telegram->sendMessage(
            $chatId,
            "✅ <b>{$typeLabel} creato</b>\n{$counterparty}: {$formatted}"
        );
    }

    private function userCanModifyHousehold(User $user): bool
    {
        if (! $user->active_household_id) {
            return false;
        }

        $membership = $user->households()
            ->where('households.id', $user->active_household_id)
            ->first();

        if (! $membership) {
            return false;
        }

        $permissions = json_decode($membership->pivot->permissions ?? '{}', true);

        return ($permissions['manage'] ?? false) === true;
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
            $lines[] = "• <b>{$account->name}</b>: {$sign}".number_format($balance, 2, ',', '.')." {$account->currency_code}";
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
            $date = $tx->date ? Carbon::parse($tx->date)->format('d/m') : '';
            $desc = $tx->description ?? ($tx->category?->name ?? '—');
            $currencyCode = $tx->currency_code ?: 'EUR';
            $formatted = $this->formatAmount(abs($amount), $currencyCode);
            $lines[] = "{$emoji} {$date} <b>{$sign}{$formatted}</b> – {$desc}";
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
            $lines[] = '<b>🏦 Conti</b> (usa <code>@NomeConto</code>):';
            foreach ($accounts as $account) {
                $lines[] = "  • {$account->name}";
            }
        }

        $lines[] = '';

        if ($categories->isNotEmpty()) {
            $lines[] = '<b>🏷️ Categorie</b> (usa <code>#NomeCategoria</code>):';
            $expenseCategories = $categories->where('type', 'expense');
            $incomeCategories = $categories->where('type', 'income');

            if ($expenseCategories->isNotEmpty()) {
                $lines[] = '  <i>Uscite:</i> '.$expenseCategories->pluck('name')->join(', ');
            }
            if ($incomeCategories->isNotEmpty()) {
                $lines[] = '  <i>Entrate:</i> '.$incomeCategories->pluck('name')->join(', ');
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

        // Parsing avanzato: importo, descrizione, tipo, conto, categoria, data, valuta
        $parsed = $this->parseTextMessage($text);
        $amount = $parsed['amount'];
        $description = $parsed['description'];
        $type = $parsed['type'];
        $date = $parsed['date'];

        // Risolvi conto e categoria per nome
        [$accountId, $resolvedAccount] = $this->resolveAccountByName($parsed['account_name'], $user);
        [$categoryId, $resolvedCategory] = $this->resolveCategoryByName($parsed['category_name'], $user);

        if ($type === 'expense' && $resolvedAccount?->isSavingsDeposit()) {
            $accountId = null;
            $resolvedAccount = null;
        }

        $effectiveCurrency = $this->resolveCurrencyForUser($parsed['currency'], $user);
        $exchangeRate = null;
        $amountBase = null;
        if ($amount !== null) {
            if ($parsed['manual_rate'] !== null && $parsed['manual_rate'] > 0) {
                $exchangeRate = $effectiveCurrency === CurrencyConverter::BASE_CURRENCY
                    ? 1.0
                    : $parsed['manual_rate'];
                $amountBase = round($amount * $exchangeRate, 2);
            } else {
                $snapshot = $this->currency->snapshot($amount, $effectiveCurrency, Carbon::parse($date));
                $exchangeRate = $snapshot['exchange_rate_to_base'];
                $amountBase = $snapshot['amount_base'];
            }
        }

        $item = InboxItem::create([
            'user_id' => $user->id,
            'household_id' => $user->active_household_id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'type' => $type,
            'raw_text' => $text,
            'amount' => $amount,
            'currency_code' => $amount !== null ? $effectiveCurrency : null,
            'exchange_rate_to_base' => $exchangeRate,
            'amount_base' => $amountBase,
            'description' => $description,
            'transaction_date' => $date,
            'account_id' => $accountId,
            'category_id' => $categoryId,
        ]);

        AppNotification::create([
            'user_id' => $user->id,
            'title' => $type === 'income' ? '📈 Nuova entrata in Inbox' : '💸 Nuova uscita in Inbox',
            'message' => $amount !== null
                ? 'Ricevuto da Telegram: '.($description ?? $text).' — '.$this->formatAmount((float) $amount, $effectiveCurrency)
                : 'Messaggio Telegram salvato in Inbox: '.mb_strimwidth($text, 0, 80, '…'),
            'notification_key' => 'inbox_telegram_'.$item->id,
        ]);

        if ($amount !== null) {
            $amountFormatted = $this->formatAmount((float) $amount, $effectiveCurrency);
            $typeEmoji = $type === 'income' ? '📈' : '💸';
            $typeLabel = $type === 'income' ? 'Entrata' : 'Uscita';
            $preview = "{$typeEmoji} <b>{$typeLabel}: {$amountFormatted}</b>".($description ? " – {$description}" : '');

            $extras = [];
            if ($effectiveCurrency !== CurrencyConverter::BASE_CURRENCY && $amountBase !== null) {
                $eurFormatted = '€'.number_format((float) $amountBase, 2, ',', '.');
                $rateLabel = $parsed['manual_rate'] !== null ? ' (rate manuale)' : '';
                $extras[] = "≈ {$eurFormatted}{$rateLabel}";
            }
            if ($accountId && $resolvedAccount) {
                $extras[] = "🏦 {$resolvedAccount->name}";
            } elseif ($parsed['account_name'] !== null) {
                if ($type === 'expense') {
                    $namedAccount = Account::where('household_id', $user->active_household_id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($parsed['account_name'])])
                        ->first();
                    if ($namedAccount?->isSavingsDeposit()) {
                        $extras[] = '⚠️ I conti deposito non sono disponibili per le uscite';
                    } else {
                        $extras[] = "⚠️ Conto \"{$parsed['account_name']}\" non trovato";
                    }
                } else {
                    $extras[] = "⚠️ Conto \"{$parsed['account_name']}\" non trovato";
                }
            }
            if ($categoryId && $resolvedCategory) {
                $extras[] = "🏷️ {$resolvedCategory->name}";
            } elseif ($parsed['category_name'] !== null) {
                $extras[] = "⚠️ Categoria \"{$parsed['category_name']}\" non trovata";
            }
            if ($date && $date !== now()->toDateString()) {
                $extras[] = '📅 '.Carbon::parse($date)->format('d/m/Y');
            }

            $extrasText = ! empty($extras) ? "\n".implode(' · ', $extras) : '';

            $this->telegram->sendMessage(
                $chatId,
                "✅ <b>Ricevuto!</b>\n\n{$preview}{$extrasText}\n\n🔍 <a href=\"{$this->inboxUrl()}\">Vai all'Inbox</a> per revisionare e confermare."
            );

            if ($amount !== null && (! $accountId || ! $categoryId)) {
                $this->promptAccountOrCategorySelection($user, $chatId, $item, $type, ! $accountId, ! $categoryId);
            }
        } else {
            $this->telegram->sendMessage(
                $chatId,
                "✅ <b>Ricevuto!</b>\n\n📝 <i>{$text}</i>\n\n⚠️ Nessun importo rilevato. <a href=\"{$this->inboxUrl()}\">Vai all'Inbox</a> per completare i dati."
            );
        }
    }

    // -------------------------------------------------------------------------
    // Gestione messaggi con foto
    // -------------------------------------------------------------------------

    private function handlePhotoMessage(array $message, User $user, string $chatId): void
    {
        unset($message, $user);

        $this->telegram->sendMessage(
            $chatId,
            "📸 <b>Foto non disponibile al momento</b>\n\nPer registrare una spesa invia un messaggio di testo, ad esempio:\n<code>15.50 Supermercato</code>\n\nPuoi usare i pulsanti dopo l'invio per scegliere conto e categoria.\n\n🔍 <a href=\"{$this->inboxUrl()}\">Vai all'Inbox</a>"
        );
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    /**
     * Risolve un conto per nome nell'household dell'utente.
     * Restituisce [id|null, Account|null].
     *
     * @return array{int|null, Account|null}
     */
    private function resolveAccountByName(?string $name, User $user): array
    {
        if ($name === null) {
            return [null, null];
        }
        $account = Account::where('household_id', $user->active_household_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if (! $account) {
            $account = Account::where('household_id', $user->active_household_id)
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%'])
                ->orderByRaw('LENGTH(name) ASC')
                ->first();
        }

        return [$account?->id, $account];
    }

    /**
     * Risolve una categoria per nome nell'household dell'utente.
     * Restituisce [id|null, Category|null].
     *
     * @return array{int|null, Category|null}
     */
    private function resolveCategoryByName(?string $name, User $user): array
    {
        if ($name === null) {
            return [null, null];
        }
        $category = Category::where('household_id', $user->active_household_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if (! $category) {
            $category = Category::where('household_id', $user->active_household_id)
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($name).'%'])
                ->orderByRaw('LENGTH(name) ASC')
                ->first();
        }

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
     * Multi-currency:
     *   "30 GBP cena"                → uscita 30 GBP
     *   "£30 cena pub"               → uscita 30 GBP (simbolo)
     *   "30 GBP cena ~1.18"          → uscita 30 GBP con rate manuale 1 GBP = 1.18 EUR
     *
     * @return array{amount: float|null, description: string|null, type: string, account_name: string|null, category_name: string|null, date: string, currency: string|null, manual_rate: float|null}
     */
    private function parseTextMessage(string $text): array
    {
        $text = $this->normalizeTelegramInput($text);

        $type = 'expense';
        $account_name = null;
        $category_name = null;
        $currency = null;
        $manual_rate = null;

        // Prefisso + → entrata
        if (str_starts_with($text, '+')) {
            $type = 'income';
            $text = ltrim(substr($text, 1));
        }

        // Estrai data in formato DD/MM o DD/MM/YYYY (es. 01/03 o 01/03/2026)
        // Va estratta PRIMA di @conto e #categoria per evitare che la regex greedy
        // del tag incorpori la data nella stringa del nome (es. "#Cibo 01/03").
        $date = now()->toDateString();
        if (preg_match('/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{4}))?\b/', $text, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = isset($m[3]) && $m[3] ? (int) $m[3] : now()->year;
            try {
                $parsedDate = Carbon::createFromDate($year, $month, $day);
                $date = $parsedDate->toDateString();
                $text = trim(preg_replace('/\b\d{1,2}\/\d{1,2}(?:\/\d{4})?\b/', '', $text));
            } catch (\Throwable) {
                // data non valida, usa oggi
            }
        }

        // Override rate manuale: ~1.18 (1 unità valuta = 1.18 EUR).
        // Va estratto PRIMA di @conto/#categoria per evitare interferenze.
        if (preg_match('/(?:^|\s)~(\d+(?:[.,]\d+)?)/u', $text, $m)) {
            $manual_rate = (float) str_replace(',', '.', $m[1]);
            $text = trim(str_replace($m[0], ' ', $text));
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

        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        // Simbolo valuta prefisso davanti al numero: £30, $30, €30, ¥1000
        if (preg_match('/(?<!\w)([£€$¥])\s*(?=\d)/u', $text, $m)) {
            $currency = self::SYMBOL_TO_ISO[$m[1]] ?? null;
            $text = trim(str_replace($m[1], '', $text));
        }

        // Codice ISO 3 lettere maiuscole subito dopo il numero (es. "30 GBP cena")
        if (preg_match('/^(\d+(?:[.,]\d{1,2})?)\s+([A-Z]{3})\b\s*(.*)$/u', $text, $m)) {
            $currency = $m[2];
            $text = trim($m[1].($m[3] !== '' ? ' '.$m[3] : ''));
        } elseif (preg_match('/^(.*?)\s+(\d+(?:[.,]\d{1,2})?)\s+([A-Z]{3})\b$/u', $text, $m)) {
            // Forma "Pizza 30 GBP" (numero+codice in coda)
            $currency = $m[3];
            $text = trim($m[1].' '.$m[2]);
        }

        // Pattern: numero (opzionale decimale con . o ,) seguito da testo
        if (preg_match('/^(\d+(?:[.,]\d{1,2})?)\s+(.+)$/u', $text, $matches)) {
            $amount = (float) str_replace(',', '.', $matches[1]);
            $description = trim($matches[2]);

            return compact('amount', 'description', 'type', 'account_name', 'category_name', 'date', 'currency', 'manual_rate');
        }

        // Pattern: testo seguito da numero
        if (preg_match('/^(.+)\s+(\d+(?:[.,]\d{1,2})?)$/u', $text, $matches)) {
            $amount = (float) str_replace(',', '.', $matches[2]);
            $description = trim($matches[1]);
            $description = $description !== '' ? $description : null;

            return compact('amount', 'description', 'type', 'account_name', 'category_name', 'date', 'currency', 'manual_rate');
        }

        // Solo numero
        if (preg_match('/^(\d+(?:[.,]\d{1,2})?)$/', $text, $matches)) {
            $amount = (float) str_replace(',', '.', $matches[1]);
            $description = null;

            return compact('amount', 'description', 'type', 'account_name', 'category_name', 'date', 'currency', 'manual_rate');
        }

        // Nessun importo trovato
        $amount = null;
        $description = $text !== '' ? $text : null;

        return compact('amount', 'description', 'type', 'account_name', 'category_name', 'date', 'currency', 'manual_rate');
    }

    /**
     * Restituisce la valuta effettiva per un parsing telegram in base alla
     * preferenza utente, normalizzando a EUR se il default non è impostato.
     */
    private function resolveCurrencyForUser(?string $parsed, User $user): string
    {
        $candidate = $parsed ?? $user->default_currency_code ?? 'EUR';

        return strtoupper($candidate);
    }

    /**
     * Formatta un importo per i messaggi del bot anteponendo il simbolo
     * della valuta quando disponibile, altrimenti usando il codice ISO.
     */
    private function formatAmount(float $amount, string $currencyCode): string
    {
        $isoToSymbol = array_flip(self::SYMBOL_TO_ISO);
        $formatted = number_format($amount, 2, ',', '.');

        if (isset($isoToSymbol[$currencyCode])) {
            return $isoToSymbol[$currencyCode].$formatted;
        }

        return $formatted.' '.$currencyCode;
    }

    private function inboxUrl(): string
    {
        return URL::route('inbox.index', absolute: true);
    }

    private function normalizeTelegramInput(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $text = preg_replace('/(\d)[.,](?=\s)/u', '$1', $text) ?? $text;
        $text = preg_replace('/(\d),(\d{1,2})(?!\d)/u', '$1.$2', $text) ?? $text;

        return trim($text);
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');
        $user = User::where('telegram_chat_id', $chatId)->first();

        if (! $user || $data === '') {
            $this->telegram->answerCallbackQuery($callbackId);

            return;
        }

        if (str_starts_with($data, 'account:')) {
            $accountId = (int) substr($data, 8);
            $pending = Cache::get("telegram_pending:{$chatId}");
            if (is_array($pending) && isset($pending['inbox_item_id'])) {
                $item = InboxItem::where('user_id', $user->id)->find($pending['inbox_item_id']);
                if ($item) {
                    $account = Account::find($accountId);
                    $transactionType = (string) ($pending['type'] ?? $item->type ?? 'expense');
                    if ($transactionType === 'expense' && $account?->isSavingsDeposit()) {
                        $this->telegram->answerCallbackQuery(
                            $callbackId,
                            'I conti deposito non sono disponibili per le uscite',
                            true,
                        );

                        return;
                    }

                    $item->update(['account_id' => $accountId]);
                    $pending['account_id'] = $accountId;
                    Cache::put("telegram_pending:{$chatId}", $pending, now()->addMinutes(15));
                    $this->telegram->answerCallbackQuery($callbackId, 'Conto selezionato');
                    if (empty($pending['category_id'])) {
                        $this->sendCategoryKeyboard($user, $chatId, (string) ($pending['type'] ?? 'expense'));

                        return;
                    }
                }
            }
        }

        if (str_starts_with($data, 'household:')) {
            $householdId = (int) substr($data, 10);
            if ($user->households()->where('households.id', $householdId)->exists()) {
                $user->update(['active_household_id' => $householdId]);
                $name = Household::find($householdId)?->name ?? 'Nucleo';
                $this->telegram->answerCallbackQuery($callbackId, 'Nucleo attivato');
                $this->telegram->sendMessage($chatId, "✅ Nucleo attivo: <b>{$name}</b>");

                return;
            }
        }

        if (str_starts_with($data, 'category:')) {
            $categoryId = (int) substr($data, 9);
            $pending = Cache::get("telegram_pending:{$chatId}");
            if (is_array($pending) && isset($pending['inbox_item_id'])) {
                $item = InboxItem::where('user_id', $user->id)->find($pending['inbox_item_id']);
                if ($item) {
                    $item->update(['category_id' => $categoryId]);
                    Cache::forget("telegram_pending:{$chatId}");
                    $this->telegram->answerCallbackQuery($callbackId, 'Categoria selezionata');
                    $this->telegram->sendMessage(
                        $chatId,
                        "✅ Dettagli aggiornati! <a href=\"{$this->inboxUrl()}\">Vai all'Inbox</a> per confermare."
                    );

                    return;
                }
            }
        }

        if ($data === 'category_manual') {
            $this->telegram->answerCallbackQuery($callbackId, 'Categoria manuale');
            $this->telegram->sendMessage($chatId, '✍️ Scrivi la categoria nel prossimo messaggio usando #NomeCategoria, poi reinvia la transazione.');

            return;
        }

        $this->telegram->answerCallbackQuery($callbackId);
    }

    private function promptAccountOrCategorySelection(
        User $user,
        string $chatId,
        InboxItem $item,
        string $type,
        bool $needAccount,
        bool $needCategory,
    ): void {
        Cache::put("telegram_pending:{$chatId}", [
            'inbox_item_id' => $item->id,
            'type' => $type,
            'account_id' => $item->account_id,
            'category_id' => $item->category_id,
        ], now()->addMinutes(15));

        if ($needAccount) {
            $this->sendAccountKeyboard($user, $chatId, $type);

            return;
        }

        if ($needCategory) {
            $this->sendCategoryKeyboard($user, $chatId, $type);
        }
    }

    private function sendAccountKeyboard(User $user, string $chatId, string $transactionType = 'expense'): void
    {
        $query = Account::where('household_id', $user->active_household_id)
            ->orderBy('name');

        if ($transactionType === 'expense') {
            $query->eligibleForExpenseTransactions();
        }

        $accounts = $query->limit(8)->get();

        if ($accounts->isEmpty()) {
            return;
        }

        $rows = [];
        $row = [];
        foreach ($accounts as $account) {
            $row[] = ['text' => $account->name, 'callback_data' => 'account:'.$account->id];
            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }

        $this->telegram->sendMessage(
            $chatId,
            '🏦 <b>Scegli il conto:</b>',
            'HTML',
            $this->telegram->inlineKeyboard($rows),
        );
    }

    private function sendCategoryKeyboard(User $user, string $chatId, string $type): void
    {
        $categories = Category::where('household_id', $user->active_household_id)
            ->where('type', $type === 'income' ? 'income' : 'expense')
            ->orderBy('name')
            ->limit(20)
            ->get();

        if ($categories->isEmpty()) {
            return;
        }

        $rows = [];
        $row = [];
        foreach ($categories as $category) {
            $label = ($category->icon ? $category->icon.' ' : '').$category->name;
            $row[] = ['text' => mb_strimwidth($label, 0, 32), 'callback_data' => 'category:'.$category->id];
            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }
        $rows[] = [[
            'text' => '✍️ Inserisci categoria manuale',
            'callback_data' => 'category_manual',
        ]];

        $this->telegram->sendMessage(
            $chatId,
            '🏷️ <b>Scegli la categoria:</b>',
            'HTML',
            $this->telegram->inlineKeyboard($rows),
        );
    }

    private function handleRecurringListCommand(User $user, string $chatId): void
    {
        $items = RecurringTransaction::where('user_id', $user->id)
            ->where('active', true)
            ->orderBy('next_date')
            ->limit(8)
            ->get(['description', 'amount', 'frequency', 'next_date']);

        if ($items->isEmpty()) {
            $this->telegram->sendMessage($chatId, '📭 Nessuna ricorrenza attiva.');

            return;
        }

        $lines = ['🔁 <b>Ricorrenze attive:</b>', ''];
        foreach ($items as $item) {
            $lines[] = "• {$item->description} — {$item->amount} ({$item->frequency}) prossimo: ".$item->next_date?->format('d/m/Y');
        }
        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    private function handleHouseholdsOverviewCommand(User $user, string $chatId): void
    {
        $households = $user->households()->withCount('users')->get(['households.id', 'households.name']);
        if ($households->isEmpty()) {
            $this->telegram->sendMessage($chatId, '⚠️ Nessuna household disponibile.');

            return;
        }
        $lines = ['🏠 <b>Panoramica household:</b>', ''];
        foreach ($households as $household) {
            $accounts = Account::where('household_id', $household->id)->count();
            $openDebts = DebtCredit::where('household_id', $household->id)->whereIn('status', ['open', 'overdue'])->count();
            $marker = $household->id === $user->active_household_id ? '✅ ' : '• ';
            $lines[] = "{$marker}<b>{$household->name}</b> — membri {$household->users_count}, conti {$accounts}, debiti/crediti aperti {$openDebts}";
        }
        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }
}
