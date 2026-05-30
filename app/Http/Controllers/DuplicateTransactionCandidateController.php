<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveDuplicateTransactionRequest;
use App\Models\DuplicateTransactionCandidate;
use App\Models\Transaction;
use App\Services\DuplicateTransactionCandidateService;
use App\Services\DuplicateTransactionDetectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DuplicateTransactionCandidateController extends Controller
{
    public function __construct(
        private readonly DuplicateTransactionCandidateService $duplicateService,
        private readonly DuplicateTransactionDetectionService $detectionService,
    ) {}

    public function index(): Response
    {
        $items = DuplicateTransactionCandidate::with([
            'primaryTransaction.account:id,name,currency_code',
            'primaryTransaction.category:id,name,color,icon,type',
            'primaryTransaction.tags:id,name,color',
            'primaryTransaction.user:id,name',
            'primaryTransaction.recurringTransaction:id,description,frequency,end_date',
            'candidateTransaction.account:id,name,currency_code',
            'candidateTransaction.category:id,name,color,icon,type',
            'candidateTransaction.tags:id,name,color',
            'candidateTransaction.user:id,name',
            'candidateTransaction.recurringTransaction:id,description,frequency,end_date',
        ])
            ->where('user_id', Auth::id())
            ->where('status', DuplicateTransactionCandidateService::STATUS_PENDING)
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (DuplicateTransactionCandidate $c) => ! $this->duplicateService->shouldIgnoreCandidate($c))
            ->map(fn (DuplicateTransactionCandidate $c) => $this->mapCandidate($c))
            ->values();

        $recurringDuplicateCount = $items->where('pair_type', DuplicateTransactionCandidateService::PAIR_RECURRING_VS_MANUAL)->count();

        return Inertia::render('Transactions/Duplicates', [
            'items' => $items,
            'pendingCount' => $items->count(),
            'recurringDuplicateCount' => $recurringDuplicateCount,
        ]);
    }

    public function detect(): RedirectResponse
    {
        $result = $this->detectionService->detectForUser((int) Auth::id());
        $created = $result['created'];

        if ($created === 0) {
            return back()->with('info', 'Controllo completato: nessun nuovo duplicato trovato.');
        }

        return back()->with(
            'success',
            "Controllo completato: {$created} nuov".($created === 1 ? 'a segnalazione trovata.' : 'e segnalazioni trovate.'),
        );
    }

    public function dismiss(DuplicateTransactionCandidate $candidate): RedirectResponse
    {
        $this->authorizeCandidate($candidate);

        $this->duplicateService->dismiss($candidate);

        return back()->with('success', 'Segnalazione archiviata: entrambe le transazioni sono state mantenute.');
    }

    public function keepRecurring(DuplicateTransactionCandidate $candidate): RedirectResponse
    {
        $this->authorizeCandidate($candidate);

        $this->duplicateService->keepRecurringGenerated($candidate, Auth::user());

        return back()->with('success', 'Mantenuta la transazione da ricorrenza. Il movimento inserito a mano è stato eliminato.');
    }

    public function resolveAllRecurring(): RedirectResponse
    {
        $count = $this->duplicateService->resolveAllRecurringVsManual(Auth::user());

        if ($count === 0) {
            return back()->with('info', 'Nessuna segnalazione «ricorrenza vs manuale» da risolvere.');
        }

        return back()->with('success', "Rimosse {$count} transazioni manuali duplicate, mantenute quelle generate dalle ricorrenze.");
    }

    public function remove(
        ResolveDuplicateTransactionRequest $request,
        DuplicateTransactionCandidate $candidate,
    ): RedirectResponse {
        $this->authorizeCandidate($candidate);

        $side = $request->validated('transaction_to_remove');

        $this->duplicateService->removeTransaction(
            $candidate,
            Auth::user(),
            $side,
        );

        return back()->with('success', 'Transazione duplicata eliminata. L\'altra movimentazione è stata mantenuta.');
    }

    private function authorizeCandidate(DuplicateTransactionCandidate $candidate): void
    {
        abort_unless($candidate->user_id === Auth::id(), 403);
        abort_unless(
            $candidate->status === DuplicateTransactionCandidateService::STATUS_PENDING,
            422,
            'Questa segnalazione è già stata gestita.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCandidate(DuplicateTransactionCandidate $c): array
    {
        $primary = $c->primaryTransaction;
        $candidateTx = $c->candidateTransaction;
        $pair = ($primary && $candidateTx)
            ? $this->duplicateService->classifyPair($primary, $candidateTx)
            : [
                'type' => DuplicateTransactionCandidateService::PAIR_MANUAL,
                'recurring_side' => null,
                'manual_side' => null,
                'recurring' => null,
            ];

        $recurringTemplate = $pair['recurring'] ?? null;
        $clusterIds = $this->clusterTransactionIds($c);
        $clusterSize = count($clusterIds);
        $primaryId = (int) $c->primary_transaction_id;
        $candidateId = (int) $c->candidate_transaction_id;

        $additional = [];
        $clusterSpreadDays = (int) $c->distance_days;

        if ($clusterSize > 2) {
            $clusterTransactions = Transaction::query()
                ->with([
                    'account:id,name,currency_code',
                    'category:id,name,color,icon,type',
                    'tags:id,name,color',
                    'user:id,name',
                    'recurringTransaction:id,description,frequency',
                ])
                ->whereIn('id', $clusterIds)
                ->orderBy('date')
                ->get();

            $clusterSpreadDays = abs((int) $clusterTransactions->first()->date->diffInDays(
                $clusterTransactions->last()->date
            ));

            $additional = $clusterTransactions
                ->filter(fn (Transaction $t) => ! in_array((int) $t->id, [$primaryId, $candidateId], true))
                ->map(fn (Transaction $t) => $this->mapTransactionSide($t, $pair, 'unknown'))
                ->values()
                ->all();
        }

        return [
            'id' => $c->id,
            'distance_days' => $c->distance_days,
            'cluster_size' => $clusterSize,
            'cluster_spread_days' => $clusterSpreadDays,
            'pair_type' => $pair['type'],
            'recurring_side' => $pair['recurring_side'],
            'recurring_template_label' => $recurringTemplate?->description,
            'primary' => $this->mapTransactionSide($primary, $pair, 'primary'),
            'candidate' => $this->mapTransactionSide($candidateTx, $pair, 'candidate'),
            'additional_transactions' => $additional,
        ];
    }

    /**
     * @return int[]
     */
    private function clusterTransactionIds(DuplicateTransactionCandidate $candidate): array
    {
        $ids = $candidate->cluster_transaction_ids
            ?? [$candidate->primary_transaction_id, $candidate->candidate_transaction_id];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @param  array<string, mixed>  $pair
     * @return array<string, mixed>
     */
    private function mapTransactionSide(?Transaction $transaction, array $pair, string $side): array
    {
        if ($transaction === null) {
            return [
                'transaction_id' => null,
                'date' => null,
                'amount' => 0,
                'description' => null,
                'account_name' => null,
                'currency_code' => 'EUR',
                'edit_url' => null,
                'entry_source' => 'unknown',
                'recurring_label' => null,
                'recurring_show_url' => null,
                'recurring_edit_url' => null,
                'recurring_frequency' => null,
                'recurring_is_ended' => false,
                'recurring_end_date' => null,
                'category' => null,
                'tags' => [],
                'user_name' => null,
                'is_private' => false,
                'is_tax_deductible' => false,
                'is_transfer' => false,
                'is_refund' => false,
                'created_at' => null,
            ];
        }

        $entrySource = $this->duplicateService->entrySourceForSide($side, $pair, $transaction);
        $linkedRecurring = $transaction->recurringTransaction;
        $endedTemplate = $this->duplicateService->resolveEndedRecurringTemplateForTransaction($transaction);
        $inferredTemplate = $this->duplicateService->resolveRecurringTemplateForTransaction($transaction);
        $templateRecurring = $pair['recurring'] ?? $linkedRecurring ?? $inferredTemplate ?? $endedTemplate;
        $showRecurringLink = $entrySource === 'recurring'
            || $pair['type'] === DuplicateTransactionCandidateService::PAIR_RECURRING_VS_MANUAL
            || $endedTemplate !== null
            || $inferredTemplate !== null;
        $recurringIsEnded = $templateRecurring?->isEnded() ?? false;

        return [
            'transaction_id' => $transaction->id,
            'date' => $transaction->date->format('Y-m-d'),
            'amount' => (float) $transaction->amount,
            'description' => $transaction->description,
            'account_name' => $transaction->account?->name,
            'currency_code' => $transaction->account?->currency_code ?? $transaction->currency_code ?? 'EUR',
            'edit_url' => route('transactions.edit', $transaction),
            'entry_source' => $entrySource,
            'recurring_label' => $showRecurringLink ? $templateRecurring?->description : null,
            'recurring_show_url' => $showRecurringLink && $templateRecurring
                ? route('recurring-transactions.show', $templateRecurring)
                : null,
            'recurring_edit_url' => $showRecurringLink && $templateRecurring
                ? route('recurring-transactions.edit', $templateRecurring)
                : null,
            'recurring_frequency' => $showRecurringLink ? $templateRecurring?->frequency : null,
            'recurring_is_ended' => $recurringIsEnded,
            'recurring_end_date' => $recurringIsEnded ? $templateRecurring?->end_date?->format('Y-m-d') : null,
            'category' => $transaction->category ? [
                'name' => $transaction->category->name,
                'color' => $transaction->category->color,
                'icon' => $transaction->category->icon,
            ] : null,
            'tags' => $transaction->tags->map(fn ($tag) => [
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values()->all(),
            'user_name' => $transaction->user?->name,
            'is_private' => (bool) $transaction->is_private,
            'is_tax_deductible' => (bool) $transaction->is_tax_deductible,
            'is_transfer' => $transaction->transfer_id !== null,
            'is_refund' => $transaction->refund_id !== null,
            'created_at' => $transaction->created_at?->format('d/m/Y H:i'),
        ];
    }
}
