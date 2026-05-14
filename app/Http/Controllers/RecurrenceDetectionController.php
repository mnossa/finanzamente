<?php

namespace App\Http\Controllers;

use App\Models\RecurringTransactionSuggestion;
use App\Models\Transaction;
use App\Services\RecurrenceDetectionService;
use App\Services\RecurringTransactionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RecurrenceDetectionController extends Controller
{
    public function __construct(
        private readonly RecurrenceDetectionService $detectionService,
        private readonly RecurringTransactionService $recurringService,
    ) {}

    /**
     * Mostra l'elenco dei suggerimenti di ricorrenza in attesa.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $rawSuggestions = RecurringTransactionSuggestion::with([
            'account:id,name,currency_code',
            'category:id,name,color,icon,type',
        ])
            ->whereHas('account', fn ($q) => $q->where('household_id', $householdId))
            ->pending()
            ->orderByDesc('confidence')
            ->orderByDesc('created_at')
            ->get();

        $suggestions = $rawSuggestions
            ->map(fn ($s) => $this->formatSuggestion($s))
            ->values();

        $suggestions = $this->addAmountChangeGuidance($suggestions);

        return Inertia::render('RecurringTransactions/Suggestions', [
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Avvia il rilevamento per l'household attiva e reindirizza all'elenco.
     */
    public function detect(): RedirectResponse
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $created = $this->detectionService->detectForHousehold($householdId);

        if ($created > 0) {
            return redirect()->route('recurrence-detection.index')
                ->with('success', "Rilevamento completato: {$created} nuovi suggerimenti trovati.");
        }

        return redirect()->route('recurrence-detection.index')
            ->with('info', 'Nessuna nuova ricorrenza rilevata.');
    }

    /**
     * Accetta un suggerimento: crea la ricorrenza e collega le transazioni.
     */
    public function accept(Request $request, RecurringTransactionSuggestion $suggestion): RedirectResponse
    {
        $this->authorizeHousehold($suggestion);

        if (! $suggestion->isPending()) {
            return redirect()->route('recurrence-detection.index')
                ->with('error', 'Il suggerimento non è più in attesa.');
        }

        $validated = $request->validate([
            'mode' => 'nullable|in:auto,active,closed,closed_fill_gaps,active_fill_gaps',
        ]);

        try {
            $result = $this->detectionService->acceptSuggestion(
                $suggestion,
                $this->recurringService,
                $validated['mode'] ?? 'auto'
            );
        } catch (DomainException $e) {
            return redirect()->route('recurrence-detection.index')
                ->with('error', $e->getMessage());
        }

        $message = 'Ricorrenza creata e transazioni collegate.';
        if ($result->removedFutureTransactionCount > 0) {
            $message .= sprintf(
                ' Rimosse %d transazioni future già registrate (verranno ricreate allo scadenziario dalla ricorrenza).',
                $result->removedFutureTransactionCount
            );
        }

        return redirect()->route('recurring-transactions.show', $result->recurring->id)
            ->with('success', $message);
    }

    /**
     * Ignora un suggerimento: non verrà più mostrato.
     */
    public function ignore(RecurringTransactionSuggestion $suggestion): RedirectResponse
    {
        $this->authorizeHousehold($suggestion);

        if (! $suggestion->isPending()) {
            return redirect()->route('recurrence-detection.index')
                ->with('error', 'Il suggerimento non è più in attesa.');
        }

        $suggestion->update(['status' => 'ignored']);

        return redirect()->route('recurrence-detection.index')
            ->with('success', 'Suggerimento ignorato.');
    }

    // -------------------------------------------------------------------------

    private function formatSuggestion(RecurringTransactionSuggestion $s): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            (array) ($s->transaction_ids ?? [])
        )));

        // Include soft-delete: altrimenti l'anteprima risulta vuota se l'utente ha eliminato
        // movimenti dopo il rilevamento, e acceptSuggestion andrebbe in errore su ->first().
        $transactions = Transaction::withTrashed()
            ->whereIn('id', $ids)
            ->where('account_id', $s->account_id)
            ->orderBy('date')
            ->get(['id', 'date', 'description', 'amount'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'date' => $t->date->format('Y-m-d'),
                'description' => $t->description,
                'amount' => (float) $t->amount,
            ]);

        $lastTransactionDate = $transactions->isNotEmpty()
            ? Carbon::parse($transactions->last()['date'])
            : null;
        $willAutoClose = $lastTransactionDate
            ? $this->detectionService->shouldAutoCloseAtLastDate(
                $s->detected_frequency,
                $lastTransactionDate,
                $s->description,
                $s->account_id
            )
            : false;
        $gapInsights = $this->detectionService->calculateGapInsights(
            $s->detected_frequency,
            $transactions->map(fn ($t) => Carbon::parse($t['date']))->all()
        );

        return [
            'id' => $s->id,
            'amount' => (float) $s->amount,
            'currency_code' => $s->currency_code,
            'description' => $s->description,
            'detected_frequency' => $s->detected_frequency,
            'confidence' => (float) $s->confidence,
            'confidence_label' => $s->confidenceLabel(),
            'transaction_count' => $transactions->count(),
            'transactions' => $transactions,
            'will_auto_close' => $willAutoClose,
            'auto_close_end_date' => $willAutoClose && $lastTransactionDate
                ? $lastTransactionDate->format('Y-m-d')
                : null,
            'has_gaps' => $gapInsights['has_gaps'],
            'missing_occurrences' => $gapInsights['missing_occurrences'],
            'largest_gap_days' => $gapInsights['largest_gap_days'],
            'has_internal_gaps' => $gapInsights['has_internal_gaps'],
            'internal_missing_occurrences' => $gapInsights['internal_missing_occurrences'],
            'has_trailing_gap' => $gapInsights['has_trailing_gap'],
            'trailing_missing_occurrences' => $gapInsights['trailing_missing_occurrences'],
            'first_transaction_date' => $transactions->isNotEmpty()
                ? $transactions->first()['date']
                : null,
            'last_transaction_date' => $transactions->isNotEmpty()
                ? $transactions->last()['date']
                : null,
            'amount_change_guidance' => null,
            'account' => [
                'id' => $s->account->id,
                'name' => $s->account->name,
                'currency_code' => $s->account->currency_code,
            ],
            'category' => $s->category ? [
                'id' => $s->category->id,
                'name' => $s->category->name,
                'color' => $s->category->color,
                'icon' => $s->category->icon,
                'type' => $s->category->type,
            ] : null,
        ];
    }

    /**
     * Verifica che il suggerimento appartenga all'household attiva dell'utente.
     */
    private function authorizeHousehold(RecurringTransactionSuggestion $suggestion): void
    {
        $householdId = Auth::user()->active_household_id;
        $suggestion->load('account');

        abort_if(
            $suggestion->account->household_id !== $householdId,
            403,
            'Accesso non autorizzato.'
        );
    }

    /**
     * Aggiunge guida UX quando esistono suggerimenti "gemelli" con stesso contesto
     * ma importo diverso in periodi consecutivi (tipico cambio prezzo abbonamento).
     */
    private function addAmountChangeGuidance(Collection $suggestions): array
    {
        $grouped = $suggestions->groupBy(function (array $suggestion) {
            return implode('|', [
                $suggestion['account']['id'],
                (string) data_get($suggestion, 'category.id', 'none'),
                $suggestion['detected_frequency'],
                mb_strtolower(trim((string) ($suggestion['description'] ?? ''))),
            ]);
        });

        $guidanceById = [];

        foreach ($grouped as $group) {
            $withRanges = $group
                ->filter(fn (array $s) => $s['first_transaction_date'] && $s['last_transaction_date'])
                ->values();

            if ($withRanges->count() < 2) {
                continue;
            }

            $sorted = $withRanges->sortBy('first_transaction_date')->values();
            for ($i = 0; $i < $sorted->count() - 1; $i++) {
                $previous = $sorted[$i];
                $next = $sorted[$i + 1];

                // Se importo uguale, non è variazione importo.
                if ((float) $previous['amount'] === (float) $next['amount']) {
                    continue;
                }

                $guidanceById[$previous['id']] = [
                    'pair_with_suggestion_id' => $next['id'],
                    'pair_amount' => $next['amount'],
                    'recommended_mode' => 'closed',
                    'variant' => 'amount_change_previous',
                    'message' => 'Possibile cambio importo rilevato: questa sembra la fase precedente.',
                ];

                $guidanceById[$next['id']] = [
                    'pair_with_suggestion_id' => $previous['id'],
                    'pair_amount' => $previous['amount'],
                    'recommended_mode' => 'active',
                    'variant' => 'amount_change_next',
                    'message' => 'Possibile cambio importo rilevato: questa sembra la fase più recente.',
                ];
            }
        }

        return $suggestions->map(function (array $suggestion) use ($guidanceById) {
            $suggestion['amount_change_guidance'] = $guidanceById[$suggestion['id']] ?? null;

            return $suggestion;
        })->values()->all();
    }
}
