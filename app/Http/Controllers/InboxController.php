<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\InboxItem;
use App\Models\Transaction;
use App\Services\CurrencyConverter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * InboxController
 *
 * Gestisce la Staging Area (Inbox) delle voci in attesa di revisione.
 * Le voci non confermate (stato draft/needs_review) NON vengono conteggiate
 * nei report finali.
 */
class InboxController extends Controller
{
    public function __construct(private CurrencyConverter $currency) {}

    /**
     * Mostra l'elenco delle voci in Inbox (solo in attesa di revisione).
     */
    public function index(): Response
    {
        $user = Auth::user();

        $items = InboxItem::where('user_id', $user->id)
            ->whereIn('status', ['draft', 'needs_review'])
            ->with(['category', 'account'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $archiveCount = InboxItem::where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'rejected'])
            ->count();

        $recentArchive = InboxItem::where('user_id', $user->id)
            ->whereIn('status', ['confirmed', 'rejected'])
            ->with(['category'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (InboxItem $item) => [
                'id' => $item->id,
                'status' => $item->status,
                'type' => $item->type,
                'amount' => $item->amount,
                'currency_code' => $item->currency_code,
                'description' => $item->description,
                'transaction_date' => $item->transaction_date?->format('Y-m-d'),
                'category' => $item->category ? [
                    'id' => $item->category->id,
                    'name' => $item->category->name,
                ] : null,
                'updated_at' => $item->updated_at?->toIso8601String(),
            ]);

        $accounts = Account::where('household_id', $user->active_household_id)
            ->orderBy('name')
            ->get()
            ->map(fn (Account $account) => $account->toTransactionFormOption())
            ->values()
            ->all();

        $categories = Category::where('household_id', $user->active_household_id)
            ->select('id', 'name', 'type', 'color')
            ->orderBy('name')
            ->get();

        // Conteggio voci da verificare per il badge nella navbar
        $pendingCount = InboxItem::where('user_id', $user->id)
            ->whereIn('status', ['draft', 'needs_review'])
            ->count();

        return Inertia::render('Inbox/Index', [
            'items' => $items,
            'accounts' => $accounts,
            'categories' => $categories,
            'pendingCount' => $pendingCount,
            'archiveCount' => $archiveCount,
            'recentArchive' => $recentArchive,
            'telegramLinked' => $user->telegram_chat_id !== null,
            'telegramBotUsername' => config('services.telegram.bot_username'),
        ]);
    }

    /**
     * Aggiorna i dati di una voce in Inbox (revisione manuale).
     */
    public function update(Request $request, InboxItem $inboxItem)
    {
        $this->authorizeItem($inboxItem);

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'type' => 'nullable|in:income,expense',
            'description' => 'nullable|string|max:255',
            'transaction_date' => 'nullable|date',
            'category_id' => 'nullable|exists:categories,id',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        if ($accountError = $this->savingsDepositExpenseError(
            $validated['type'] ?? $inboxItem->type,
            isset($validated['account_id']) ? (int) $validated['account_id'] : $inboxItem->account_id,
        )) {
            return back()->withErrors(['account_id' => $accountError]);
        }

        $inboxItem->update($validated);

        return back()->with('success', 'Voce aggiornata con successo.');
    }

    /**
     * Conferma una voce: crea la transazione definitiva e marca la voce come
     * confirmed. Le voci non confermate non incidono sui report.
     * Accetta account_id e category_id opzionali dalla request per sovrascrivere
     * quelli già salvati nella voce.
     */
    public function confirm(Request $request, InboxItem $inboxItem)
    {
        $this->authorizeItem($inboxItem);

        if ($inboxItem->status === 'confirmed') {
            return back()->with('error', 'Questa voce è già stata confermata.');
        }

        // Se manca l'importo non si può confermare
        if ($inboxItem->amount === null) {
            return back()->with('error', 'Impossibile confermare: importo mancante. Modifica la voce prima.');
        }

        $validated = $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        // Aggiorna account e categoria se esplicitamente forniti nella request
        if (array_key_exists('account_id', $validated) && $validated['account_id'] !== null) {
            $inboxItem->account_id = $validated['account_id'];
        }
        if (array_key_exists('category_id', $validated)) {
            $inboxItem->category_id = $validated['category_id'];
        }

        // Scegli un account: quello della voce oppure il primo della household
        $accountId = $inboxItem->account_id;
        if ($accountId) {
            $selectedAccount = Account::find($accountId);
            if ($inboxItem->type === 'expense' && $selectedAccount?->isSavingsDeposit()) {
                $accountId = null;
            }
        }
        if (! $accountId) {
            $accountQuery = Account::where('household_id', Auth::user()->active_household_id);
            if ($inboxItem->type === 'expense') {
                $accountQuery->eligibleForExpenseTransactions();
            }
            $defaultAccount = $accountQuery->first();
            $accountId = $defaultAccount?->id;
        }

        if (! $accountId) {
            return back()->with('error', 'Nessun conto disponibile. Crea prima un conto.');
        }

        $account = Account::find($accountId);
        if ($accountError = $this->savingsDepositExpenseError($inboxItem->type, (int) $accountId)) {
            return back()->withErrors(['account_id' => $accountError]);
        }

        // Crea la transazione definitiva (con eventuale conversione di valuta)
        $transaction = Transaction::create($this->buildTransactionPayload($inboxItem, $account));

        // Marca la voce come confermata
        $inboxItem->update([
            'status' => 'confirmed',
            'transaction_id' => $transaction->id,
        ]);

        // Segna la notifica correlata come letta
        $this->markRelatedNotificationRead($inboxItem);

        return back()->with('success', 'Voce confermata e transazione creata con successo.');
    }

    /**
     * Rifiuta (scarta) una voce in Inbox.
     */
    public function reject(InboxItem $inboxItem)
    {
        $this->authorizeItem($inboxItem);

        $inboxItem->update(['status' => 'rejected']);

        // Segna la notifica correlata come letta
        $this->markRelatedNotificationRead($inboxItem);

        return back()->with('success', 'Voce scartata.');
    }

    /**
     * Conferma tutte le voci in attesa (draft/needs_review) con importo disponibile.
     */
    public function confirmAll(Request $request)
    {
        $user = Auth::user();

        $defaultAccount = Account::where('household_id', $user->active_household_id)->first();
        $defaultExpenseAccount = Account::where('household_id', $user->active_household_id)
            ->eligibleForExpenseTransactions()
            ->first();

        $pending = InboxItem::where('user_id', $user->id)
            ->whereIn('status', ['draft', 'needs_review'])
            ->whereNotNull('amount')
            ->get();

        $confirmed = 0;
        $skipped = 0;

        foreach ($pending as $item) {
            $accountId = $item->account_id;
            if ($accountId) {
                $selectedAccount = Account::find($accountId);
                if ($item->type === 'expense' && $selectedAccount?->isSavingsDeposit()) {
                    $accountId = null;
                }
            }

            if (! $accountId) {
                $accountId = $item->type === 'expense'
                    ? $defaultExpenseAccount?->id
                    : $defaultAccount?->id;
            }

            if (! $accountId) {
                $skipped++;

                continue;
            }

            $account = Account::find($accountId);
            $transaction = Transaction::create($this->buildTransactionPayload($item, $account));

            $item->update([
                'status' => 'confirmed',
                'transaction_id' => $transaction->id,
            ]);

            $this->markRelatedNotificationRead($item);
            $confirmed++;
        }

        $message = "Confermate {$confirmed} voci.";
        if ($skipped > 0) {
            $message .= " {$skipped} voci saltate (nessun conto disponibile).";
        }

        return back()->with('success', $message);
    }

    /**
     * Scarta tutte le voci in attesa (draft/needs_review).
     */
    public function rejectAll()
    {
        $user = Auth::user();

        $pending = InboxItem::where('user_id', $user->id)
            ->whereIn('status', ['draft', 'needs_review'])
            ->get();

        foreach ($pending as $item) {
            $item->update(['status' => 'rejected']);
            $this->markRelatedNotificationRead($item);
        }

        $count = $pending->count();

        return back()->with('success', "Scartate {$count} voci.");
    }

    /**
     * Elimina definitivamente una voce in Inbox.
     */
    public function destroy(InboxItem $inboxItem)
    {
        $this->authorizeItem($inboxItem);

        // Rimuovi l'immagine associata se presente
        if ($inboxItem->image_path && Storage::disk('private')->exists($inboxItem->image_path)) {
            Storage::disk('private')->delete($inboxItem->image_path);
        }

        $inboxItem->delete();

        return back()->with('success', 'Voce eliminata.');
    }

    /**
     * Restituisce l'immagine di uno scontrino (per la visualizzazione in-app).
     */
    public function image(InboxItem $inboxItem)
    {
        $this->authorizeItem($inboxItem);

        if (! $inboxItem->image_path || ! Storage::disk('private')->exists($inboxItem->image_path)) {
            abort(404, 'Immagine non disponibile.');
        }

        $content = Storage::disk('private')->get($inboxItem->image_path);
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: 'image/jpeg';

        return response($content, 200)->header('Content-Type', $mimeType);
    }

    /**
     * Costruisce il payload di una `transactions` a partire da una voce Inbox.
     *
     * Regola architetturale: la transazione è sempre nella valuta del conto
     * (`account.currency_code`). Se la voce Inbox è in valuta diversa (es. l'utente
     * ha scritto "30 GBP" via Telegram ma il conto è EUR), convertiamo l'importo
     * applicando il rate manuale eventualmente scelto dall'utente o, in mancanza,
     * il rate del giorno via Frankfurter. La valuta originale viene comunque
     * tracciata in `original_amount` / `original_currency_code` per riconciliazione.
     *
     * @return array<string, mixed>
     */
    private function buildTransactionPayload(InboxItem $item, Account $account): array
    {
        $accountCurrency = $account->currency_code ?: CurrencyConverter::BASE_CURRENCY;
        $itemCurrency = $item->currency_code ?: CurrencyConverter::BASE_CURRENCY;
        $rawAmount = abs((float) $item->amount);
        $date = $item->transaction_date ? Carbon::parse($item->transaction_date) : now();

        // Manual rate ricavato dall'inbox (se presente): "1 itemCurrency = X EUR".
        // Lo deduciamo da exchange_rate_to_base se la voce stessa l'aveva impostato.
        $manualRate = null;
        if ($item->exchange_rate_to_base !== null && $itemCurrency !== CurrencyConverter::BASE_CURRENCY) {
            $manualRate = (float) $item->exchange_rate_to_base;
        }

        $converted = $this->currency->convertToAccountCurrency(
            originalAmount: $rawAmount,
            originalCurrency: $itemCurrency,
            accountCurrency: $accountCurrency,
            date: $date,
            manualRate: $manualRate,
        );

        $signedAmount = $item->type === 'income'
            ? abs($converted['amount'])
            : -abs($converted['amount']);

        return [
            'user_id' => $item->user_id,
            'account_id' => $account->id,
            'category_id' => $item->category_id,
            'amount' => $signedAmount,
            'currency_code' => $converted['currency_code'],
            'exchange_rate_to_base' => $converted['exchange_rate_to_base'],
            'amount_base' => $item->type === 'income'
                ? abs($converted['amount_base'])
                : -abs($converted['amount_base']),
            'original_amount' => $converted['original_amount'],
            'original_currency_code' => $converted['original_currency_code'],
            'date' => $date->toDateString(),
            'description' => $item->description ?? $item->raw_text,
        ];
    }

    // -------------------------------------------------------------------------
    // Autorizzazione
    // -------------------------------------------------------------------------

    private function savingsDepositExpenseError(?string $type, ?int $accountId): ?string
    {
        if ($type !== 'expense' || ! $accountId) {
            return null;
        }

        $account = Account::query()->find($accountId);
        if ($account && $account->isSavingsDeposit()) {
            return 'I conti deposito non possono essere usati per le uscite.';
        }

        return null;
    }

    private function authorizeItem(InboxItem $item): void
    {
        if ($item->user_id !== Auth::id()) {
            abort(403, 'Non hai accesso a questa voce.');
        }
    }

    /**
     * Segna come letta la notifica in-app correlata a questa voce di Inbox.
     * Le notifiche create dal bot Telegram usano la chiave `inbox_telegram_{id}`.
     */
    private function markRelatedNotificationRead(InboxItem $item): void
    {
        AppNotification::where('user_id', $item->user_id)
            ->where('notification_key', 'inbox_telegram_'.$item->id)
            ->where('read', false)
            ->update(['read' => true]);
    }
}
