<?php

namespace App\Http\Controllers;

use App\Models\Account;
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

        // Scegli un account: quello selezionato in Inbox oppure il primo della household
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
            'amount' => -abs((float) $inboxItem->amount), // Le spese Telegram sono sempre uscite
            'currency_code' => 'EUR',
            'date' => $inboxItem->transaction_date ?? now()->toDateString(),
            'description' => $inboxItem->description ?? $inboxItem->raw_text,
        ]);

        // Marca la voce come confermata
        $inboxItem->update([
            'status' => 'confirmed',
            'transaction_id' => $transaction->id,
        ]);

        return back()->with('success', 'Voce confermata e transazione creata con successo.');
    }

    /**
     * Rifiuta (scarta) una voce in Inbox.
     */
    public function reject(InboxItem $inboxItem)
    {
        $this->authorizeItem($inboxItem);

        $inboxItem->update(['status' => 'rejected']);

        return back()->with('success', 'Voce scartata.');
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
        $mimeType = Storage::disk('private')->mimeType($inboxItem->image_path) ?? 'image/jpeg';

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
}
