<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    /**
     * Mostra l'elenco dei tag della household attiva.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $tags = Tag::where('household_id', $householdId)
            ->withCount('transactions')
            ->orderBy('name')
            ->get()
            ->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'transactions_count' => $tag->transactions_count,
                'created_at' => $tag->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('Tags/Index', [
            'tags' => $tags,
        ]);
    }

    /**
     * Mostra il form per creare un nuovo tag.
     */
    public function create(): Response
    {
        return Inertia::render('Tags/Create');
    }

    /**
     * Salva un nuovo tag.
     * Se un tag con lo stesso nome (case-insensitive) esiste già, reindirizza all'indice con un avviso.
     */
    public function store(StoreTagRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();
        $householdId = $user->active_household_id;

        $existing = Tag::findByNameForHousehold($validated['name'], $householdId);

        if ($existing) {
            return redirect()
                ->route('tags.index')
                ->with('warning', "Il tag \"{$existing->name}\" esiste già.");
        }

        Tag::create([
            'household_id' => $householdId,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
        ]);

        return redirect()
            ->route('tags.index')
            ->with('success', 'Tag creato con successo.');
    }

    /**
     * Mostra il form per modificare un tag.
     */
    public function edit(Tag $tag): Response
    {
        $this->authorizeTag($tag);

        return Inertia::render('Tags/Edit', [
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ],
        ]);
    }

    /**
     * Aggiorna un tag esistente.
     */
    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $this->authorizeTag($tag);

        $tag->update($request->validated());

        return redirect()
            ->route('tags.index')
            ->with('success', 'Tag aggiornato con successo.');
    }

    /**
     * Elimina un tag (soft delete).
     */
    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorizeTag($tag);

        // Rimuovi le associazioni con le transazioni
        $tag->transactions()->detach();
        $tag->delete();

        return redirect()
            ->route('tags.index')
            ->with('success', 'Tag eliminato con successo.');
    }

    /**
     * Cerca tag per autocomplete durante la digitazione (usato nelle form di transazione).
     * Restituisce i tag della household che corrispondono alla query.
     */
    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;
        $q = strtoupper(trim($request->get('q', '')));

        $tags = Tag::where('household_id', $householdId)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', $q.'%'))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'color']);

        return response()->json($tags);
    }

    /**
     * Verifica che l'utente possa accedere al tag.
     */
    private function authorizeTag(Tag $tag): void
    {
        $user = Auth::user();

        if ($tag->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questo tag.');
        }
    }
}
