<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\InboxItem;
use App\Models\Transaction;
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
    /**
     * Mostra l'elenco delle voci in Inbox.
     * Vengono mostrate prima le voci in attesa (draft/needs_review), poi le confermate.
     */
    public function index(): Response
    {
        $user = Auth::user();

        $items = InboxItem::where('user_id', $user->id)
            ->with(['category', 'account'])
            ->orderByRaw("CASE WHEN status IN ('draft','needs_review') THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(20);

        $accounts = Account::where('household_id', $user->active_household_id)
            ->select('id', 'name', 'currency_code')
            ->get();

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
        if (! $accountId) {
            $defaultAccount = Account::where('household_id', Auth::user()->active_household_id)->first();
            $accountId = $defaultAccount?->id;
        }

        if (! $accountId) {
            return back()->with('error', 'Nessun conto disponibile. Crea prima un conto.');
        }

        // Crea la transazione definitiva
        $transaction = Transaction::create([
            'user_id' => $inboxItem->user_id,
            'account_id' => $accountId,
            'category_id' => $inboxItem->category_id,
            'amount' => $inboxItem->type === 'income'
                ? abs((float) $inboxItem->amount)   // entrata: positivo
                : -abs((float) $inboxItem->amount),  // uscita: negativo
            'currency_code' => 'EUR',
            'date' => $inboxItem->transaction_date ?? now()->toDateString(),
            'description' => $inboxItem->description ?? $inboxItem->raw_text,
        ]);

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

        $pending = InboxItem::where('user_id', $user->id)
            ->whereIn('status', ['draft', 'needs_review'])
            ->whereNotNull('amount')
            ->get();

        $confirmed = 0;
        $skipped = 0;

        foreach ($pending as $item) {
            $accountId = $item->account_id ?? $defaultAccount?->id;

            if (! $accountId) {
                $skipped++;

                continue;
            }

            $transaction = Transaction::create([
                'user_id' => $item->user_id,
                'account_id' => $accountId,
                'category_id' => $item->category_id,
                'amount' => $item->type === 'income'
                    ? abs((float) $item->amount)
                    : -abs((float) $item->amount),
                'currency_code' => 'EUR',
                'date' => $item->transaction_date ?? now()->toDateString(),
                'description' => $item->description ?? $item->raw_text,
            ]);

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

    // -------------------------------------------------------------------------
    // Autorizzazione
    // -------------------------------------------------------------------------

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
