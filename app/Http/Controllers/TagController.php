<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $tags = Tag::forUser($user->id, $householdId)
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
     * Dettaglio tag con aggregati finanziari sul periodo selezionato.
     */
    public function show(Request $request, Tag $tag): Response
    {
        $this->authorizeTag($tag);

        $user = Auth::user();
        $householdId = (int) $user->active_household_id;

        $monthInput = $request->string('month')->toString();
        $month = $monthInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthInput)
            ? Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth()
            : Carbon::now()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $baseQuery = Transaction::query()
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->where(fn ($q) => $q->where('is_private', false)->orWhere('user_id', $user->id))
            ->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
            ->whereDate('date', '>=', $month->toDateString())
            ->whereDate('date', '<=', $monthEnd->toDateString())
            ->operationalStats();

        $income = (float) (clone $baseQuery)->where('amount', '>', 0)->sum('amount');
        $expenses = (float) abs((float) (clone $baseQuery)->where('amount', '<', 0)->sum('amount'));
        $transactionCount = (int) (clone $baseQuery)->count();

        $byCategoryBuckets = [];
        $categoryRows = (clone $baseQuery)
            ->with('category:id,name,color,icon')
            ->get(['id', 'amount', 'category_id']);

        foreach ($categoryRows as $row) {
            $key = $row->category_id === null ? 'null' : (string) $row->category_id;
            if (! isset($byCategoryBuckets[$key])) {
                $byCategoryBuckets[$key] = [
                    'category_id' => $row->category_id,
                    'name' => $row->category?->name ?? 'Senza categoria',
                    'color' => $row->category?->color,
                    'icon' => $row->category?->icon,
                    'count' => 0,
                    'income' => 0.0,
                    'expenses' => 0.0,
                ];
            }

            $amount = (float) $row->amount;
            $byCategoryBuckets[$key]['count']++;
            if ($amount > 0) {
                $byCategoryBuckets[$key]['income'] += $amount;
            } else {
                $byCategoryBuckets[$key]['expenses'] += abs($amount);
            }
        }

        $byCategory = collect($byCategoryBuckets)
            ->map(fn (array $bucket) => [
                ...$bucket,
                'income' => round($bucket['income'], 2),
                'expenses' => round($bucket['expenses'], 2),
                'net' => round($bucket['income'] - $bucket['expenses'], 2),
            ])
            ->sortByDesc(fn (array $bucket) => $bucket['expenses'] + $bucket['income'])
            ->values()
            ->all();

        $recentTransactions = (clone $baseQuery)
            ->with(['category:id,name,color,icon', 'account:id,name,currency_code', 'user:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'amount' => (float) $transaction->amount,
                'date' => $transaction->date->format('Y-m-d'),
                'description' => $transaction->description,
                'category' => $transaction->category,
                'account' => $transaction->account?->name,
                'user' => $transaction->user,
            ]);

        $monthOptions = [];
        $cursor = Carbon::now()->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $monthOptions[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('F Y'),
            ];
            $cursor->subMonth();
        }

        return Inertia::render('Tags/Show', [
            'tag' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ],
            'selectedMonth' => $month->format('Y-m'),
            'selectedMonthLabel' => $month->translatedFormat('F Y'),
            'monthOptions' => $monthOptions,
            'stats' => [
                'transaction_count' => $transactionCount,
                'income' => round($income, 2),
                'expenses' => round($expenses, 2),
                'net' => round($income - $expenses, 2),
            ],
            'byCategory' => $byCategory,
            'recentTransactions' => $recentTransactions,
            'periodFrom' => $month->toDateString(),
            'periodTo' => $monthEnd->toDateString(),
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

        $existing = Tag::findByNameForHousehold($validated['name'], $householdId, $user->id);

        if ($existing) {
            return redirect()
                ->route('tags.index')
                ->with('warning', "Il tag \"{$existing->name}\" esiste già.");
        }

        Tag::create([
            'household_id' => $householdId,
            'user_id' => $user->id,
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

        $tags = Tag::forUser($user->id, $householdId)
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

        if ($tag->household_id !== $user->active_household_id || $tag->user_id !== $user->id) {
            abort(403, 'Non hai accesso a questo tag.');
        }
    }
}
