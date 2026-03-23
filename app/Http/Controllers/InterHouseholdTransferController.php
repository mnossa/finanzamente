<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectInterHouseholdTransferRequest;
use App\Http\Requests\StoreInterHouseholdTransferRequest;
use App\Models\Account;
use App\Models\Household;
use App\Models\InterHouseholdTransfer;
use App\Services\InterHouseholdTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InterHouseholdTransferController extends Controller
{
    public function __construct(
        protected InterHouseholdTransferService $transferService
    ) {
    }

    /**
     * Mostra l'elenco dei trasferimenti inter-household
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InterHouseholdTransfer::class);

        $user = $request->user();
        $activeHouseholdId = $user->active_household_id;

        // Query base con filtri
        $query = InterHouseholdTransfer::query()
            ->with([
                'sourceHousehold',
                'destinationHousehold',
                'sourceAccount',
                'destinationAccount',
                'sourceUser',
                'destinationUser',
                'approvedBy',
                'rejectedBy',
            ])
            ->where(function ($q) use ($activeHouseholdId) {
                $q->where('source_household_id', $activeHouseholdId)
                    ->orWhere('dest_household_id', $activeHouseholdId);
            });

        // Filtro per stato
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filtro per direzione (inviati/ricevuti)
        if ($request->has('direction')) {
            if ($request->direction === 'sent') {
                $query->where('source_household_id', $activeHouseholdId);
            } elseif ($request->direction === 'received') {
                $query->where('dest_household_id', $activeHouseholdId);
            }
        }

        // Ordinamento
        $sortBy = $request->input('sort_by', 'transfer_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $transfers = $query->paginate(20)->withQueryString();

        // echo json_Encode($transfers);
        // die();

        // dd($transfers);

        return Inertia::render('InterHouseholdTransfers/Index', [
            'transfers' => $transfers,
            'filters' => $request->only(['status', 'direction', 'sort_by', 'sort_direction']),
        ]);
    }

    /**
     * Mostra il form per creare un nuovo trasferimento
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', InterHouseholdTransfer::class);

        $user = $request->user();
        $activeHouseholdId = $user->active_household_id;

        // Account della household attiva
        $sourceAccounts = Account::where('household_id', $activeHouseholdId)
            ->where('active', true)
            ->with('currency')
            ->get();

        // Solo le altre households a cui l'utente appartiene
        $userHouseholds = $user->households()
            ->where('households.id', '!=', $activeHouseholdId)
            ->get();

        // Household attiva (per calcolare il default exclude_from_stats lato frontend)
        $activeHousehold = $user->households()->find($activeHouseholdId);

        return Inertia::render('InterHouseholdTransfers/Create', [
            'sourceAccounts'   => $sourceAccounts,
            'userHouseholds'   => $userHouseholds->map(fn ($h) => [
                'id'                              => $h->id,
                'name'                            => $h->name,
                'exclude_inter_transfers_from_stats' => $h->exclude_inter_transfers_from_stats,
            ]),
            'activeHouseholdExcludesDefault' => $activeHousehold?->shouldExcludeInterTransfersFromStats() ?? false,
        ]);
    }

    /**
     * Salva un nuovo trasferimento
     */
    public function store(StoreInterHouseholdTransferRequest $request): RedirectResponse
    {
        $this->authorize('create', InterHouseholdTransfer::class);

        try {
            $transfer = $this->transferService->createTransfer(
                $request->validated(),
                $request->user()
            );

            return redirect()
                ->route('inter-household-transfers.show', $transfer)
                ->with('success', 'Trasferimento completato con successo. Le transazioni sono state create.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Mostra i dettagli di un trasferimento
     */
    public function show(InterHouseholdTransfer $interHouseholdTransfer): Response
    {
        $this->authorize('view', $interHouseholdTransfer);

        $interHouseholdTransfer->load([
            'sourceHousehold',
            'destinationHousehold',
            'sourceAccount',
            'destinationAccount',
            'sourceUser',
            'destinationUser',
            'sourceTransaction',
            'destinationTransaction',
            'approvedBy',
            'rejectedBy',
        ]);

        return Inertia::render('InterHouseholdTransfers/Show', [
            'transfer' => $interHouseholdTransfer,
        ]);
    }

    /**
     * Approva un trasferimento
     */
    public function approve(InterHouseholdTransfer $interHouseholdTransfer): RedirectResponse
    {
        $this->authorize('approve', $interHouseholdTransfer);

        try {
            $this->transferService->approveTransfer($interHouseholdTransfer, auth()->user());

            return redirect()
                ->route('inter-household-transfers.show', $interHouseholdTransfer)
                ->with('success', 'Trasferimento approvato con successo. Le transazioni sono state create.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Rifiuta un trasferimento
     */
    public function reject(
        RejectInterHouseholdTransferRequest $request,
        InterHouseholdTransfer $interHouseholdTransfer
    ): RedirectResponse {
        $this->authorize('reject', $interHouseholdTransfer);

        try {
            $this->transferService->rejectTransfer(
                $interHouseholdTransfer,
                auth()->user(),
                $request->input('rejection_reason')
            );

            return redirect()
                ->route('inter-household-transfers.show', $interHouseholdTransfer)
                ->with('success', 'Trasferimento rifiutato.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Annulla un trasferimento
     */
    public function cancel(InterHouseholdTransfer $interHouseholdTransfer): RedirectResponse
    {
        $this->authorize('cancel', $interHouseholdTransfer);

        try {
            $this->transferService->cancelTransfer($interHouseholdTransfer, auth()->user());

            return redirect()
                ->route('inter-household-transfers.show', $interHouseholdTransfer)
                ->with('success', 'Trasferimento annullato.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Elimina un trasferimento
     */
    public function destroy(InterHouseholdTransfer $interHouseholdTransfer): RedirectResponse
    {
        $this->authorize('delete', $interHouseholdTransfer);

        try {
            $this->transferService->deleteTransfer($interHouseholdTransfer);

            return redirect()
                ->route('inter-household-transfers.index')
                ->with('success', 'Trasferimento eliminato con successo.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * API endpoint per ottenere gli account di una household
     */
    public function getHouseholdAccounts(Household $household)
    {
        // Verifica che l'utente abbia accesso a questa household
        if (!$household->users()->where('users.id', auth()->id())->exists()) {
            abort(403, 'Non hai accesso a questa household.');
        }

        $accounts = Account::where('household_id', $household->id)
            ->where('active', true)
            ->with('currency')
            ->get();

        return response()->json($accounts);
    }
}
