<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinancialGoalRequest;
use App\Http\Requests\UpdateFinancialGoalRequest;
use App\Models\Currency;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class FinancialGoalController extends Controller
{
    /**
     * Mostra l'elenco degli obiettivi finanziari della household attiva.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $goals = FinancialGoal::with(['user:id,name', 'currency:code,symbol'])
            ->where('household_id', $householdId)
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'reached' THEN 1 WHEN 'cancelled' THEN 2 ELSE 3 END")
            ->orderBy('target_date')
            ->orderBy('name')
            ->get()
            ->map(function ($goal) {
                return [
                    'id' => $goal->id,
                    'name' => $goal->name,
                    'description' => $goal->description,
                    'target_amount' => (float) $goal->target_amount,
                    'current_amount' => (float) $goal->current_amount,
                    'remaining_amount' => $goal->remaining_amount,
                    'progress_percentage' => round($goal->progress_percentage, 1),
                    'currency' => [
                        'code' => $goal->currency->code ?? $goal->currency_code,
                        'symbol' => $goal->currency->symbol ?? '€',
                    ],
                    'target_date' => $goal->target_date?->format('Y-m-d'),
                    'status' => $goal->status,
                    'status_label' => FinancialGoal::STATUSES[$goal->status] ?? $goal->status,
                    'is_overdue' => $goal->isOverdue(),
                    'icon' => $goal->icon,
                    'color' => $goal->color,
                    'user' => [
                        'id' => $goal->user->id,
                        'name' => $goal->user->name,
                    ],
                ];
            });

        // Calcola statistiche
        $inProgressGoals = $goals->where('status', 'in_progress');
        $stats = [
            'total_goals' => $goals->count(),
            'in_progress' => $inProgressGoals->count(),
            'reached' => $goals->where('status', 'reached')->count(),
            'total_target' => $inProgressGoals->sum('target_amount'),
            'total_current' => $inProgressGoals->sum('current_amount'),
        ];

        return Inertia::render('FinancialGoals/Index', [
            'goals' => $goals,
            'stats' => $stats,
            'statuses' => FinancialGoal::STATUSES,
        ]);
    }

    /**
     * Mostra il form per creare un nuovo obiettivo finanziario.
     */
    public function create(): Response
    {
        $currencies = Currency::orderBy('code')->get(['code', 'name', 'symbol']);

        return Inertia::render('FinancialGoals/Create', [
            'currencies' => $currencies,
            'suggestedIcons' => FinancialGoal::SUGGESTED_ICONS,
        ]);
    }

    /**
     * Salva un nuovo obiettivo finanziario.
     */
    public function store(StoreFinancialGoalRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Limite piano Base: massimo 1 obiettivo finanziario attivo
        if (! $user->isPro()) {
            $max = config('plans.base_limits.max_financial_goals', 1);
            $count = FinancialGoal::where('household_id', $user->active_household_id)
                ->where('status', 'in_progress')
                ->count();
            if ($count >= $max) {
                return redirect()->route('financial-goals.create')
                    ->with('error', "Hai raggiunto il limite di {$max} obiettivo finanziario attivo del piano Base. Passa a Pro per obiettivi illimitati.");
            }
        }

        $validated = $request->validated();

        FinancialGoal::create([
            'household_id' => $user->active_household_id,
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'target_amount' => $validated['target_amount'],
            'current_amount' => $validated['current_amount'] ?? 0,
            'currency_code' => $validated['currency_code'],
            'target_date' => $validated['target_date'] ?? null,
            'icon' => $validated['icon'] ?? '🎯',
            'color' => $validated['color'] ?? null,
            'status' => 'in_progress',
        ]);

        return redirect()
            ->route('financial-goals.index')
            ->with('success', 'Obiettivo creato con successo.');
    }

    /**
     * Mostra i dettagli di un obiettivo finanziario.
     */
    public function show(FinancialGoal $financialGoal): Response
    {
        $this->authorizeFinancialGoal($financialGoal);

        $financialGoal->load(['user:id,name', 'currency:code,symbol']);

        return Inertia::render('FinancialGoals/Show', [
            'goal' => [
                'id' => $financialGoal->id,
                'name' => $financialGoal->name,
                'description' => $financialGoal->description,
                'target_amount' => (float) $financialGoal->target_amount,
                'current_amount' => (float) $financialGoal->current_amount,
                'remaining_amount' => $financialGoal->remaining_amount,
                'progress_percentage' => round($financialGoal->progress_percentage, 1),
                'currency' => [
                    'code' => $financialGoal->currency->code ?? $financialGoal->currency_code,
                    'symbol' => $financialGoal->currency->symbol ?? '€',
                ],
                'target_date' => $financialGoal->target_date?->format('Y-m-d'),
                'status' => $financialGoal->status,
                'status_label' => FinancialGoal::STATUSES[$financialGoal->status] ?? $financialGoal->status,
                'is_overdue' => $financialGoal->isOverdue(),
                'icon' => $financialGoal->icon,
                'color' => $financialGoal->color,
                'created_at' => $financialGoal->created_at->format('d/m/Y H:i'),
                'updated_at' => $financialGoal->updated_at->format('d/m/Y H:i'),
                'user' => [
                    'id' => $financialGoal->user->id,
                    'name' => $financialGoal->user->name,
                ],
            ],
            'statuses' => FinancialGoal::STATUSES,
        ]);
    }

    /**
     * Mostra il form per modificare un obiettivo finanziario.
     */
    public function edit(FinancialGoal $financialGoal): Response
    {
        $this->authorizeFinancialGoal($financialGoal);

        $currencies = Currency::orderBy('code')->get(['code', 'name', 'symbol']);

        return Inertia::render('FinancialGoals/Edit', [
            'goal' => [
                'id' => $financialGoal->id,
                'name' => $financialGoal->name,
                'description' => $financialGoal->description,
                'target_amount' => (float) $financialGoal->target_amount,
                'current_amount' => (float) $financialGoal->current_amount,
                'currency_code' => $financialGoal->currency_code,
                'target_date' => $financialGoal->target_date?->format('Y-m-d'),
                'icon' => $financialGoal->icon,
                'color' => $financialGoal->color,
            ],
            'currencies' => $currencies,
            'suggestedIcons' => FinancialGoal::SUGGESTED_ICONS,
        ]);
    }

    /**
     * Aggiorna un obiettivo finanziario esistente.
     */
    public function update(UpdateFinancialGoalRequest $request, FinancialGoal $financialGoal): RedirectResponse
    {
        $this->authorizeFinancialGoal($financialGoal);

        $validated = $request->validated();

        $financialGoal->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'target_amount' => $validated['target_amount'],
            'current_amount' => $validated['current_amount'],
            'currency_code' => $validated['currency_code'],
            'target_date' => $validated['target_date'] ?? null,
            'icon' => $validated['icon'] ?? $financialGoal->icon,
            'color' => $validated['color'] ?? $financialGoal->color,
        ]);

        // Verifica se l'obiettivo è stato raggiunto
        if ($financialGoal->isReached() && $financialGoal->status === 'in_progress') {
            $financialGoal->update(['status' => 'reached']);
        }

        return redirect()
            ->route('financial-goals.index')
            ->with('success', 'Obiettivo aggiornato con successo.');
    }

    /**
     * Aggiunge un contributo all'obiettivo.
     */
    public function contribute(FinancialGoal $financialGoal): RedirectResponse
    {
        $this->authorizeFinancialGoal($financialGoal);

        $amount = request()->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ])['amount'];

        $newAmount = (float) $financialGoal->current_amount + $amount;
        $financialGoal->current_amount = $newAmount;

        // Verifica se l'obiettivo è stato raggiunto
        if ($financialGoal->isReached() && $financialGoal->status === 'in_progress') {
            $financialGoal->status = 'reached';
        }

        $financialGoal->save();

        $message = $financialGoal->status === 'reached'
            ? '🎉 Obiettivo raggiunto! Congratulazioni!'
            : 'Contributo aggiunto con successo.';

        return redirect()
            ->route('financial-goals.show', $financialGoal)
            ->with('success', $message);
    }

    /**
     * Cambia lo stato dell'obiettivo.
     */
    public function changeStatus(FinancialGoal $financialGoal): RedirectResponse
    {
        $this->authorizeFinancialGoal($financialGoal);

        $status = request()->validate([
            'status' => ['required', 'in:in_progress,reached,cancelled'],
        ])['status'];

        $financialGoal->update(['status' => $status]);

        $statusLabels = FinancialGoal::STATUSES;

        return redirect()
            ->route('financial-goals.show', $financialGoal)
            ->with('success', "Stato cambiato in '{$statusLabels[$status]}'.");
    }

    /**
     * Elimina un obiettivo finanziario (soft delete).
     */
    public function destroy(FinancialGoal $financialGoal): RedirectResponse
    {
        $this->authorizeFinancialGoal($financialGoal);

        $financialGoal->delete();

        return redirect()
            ->route('financial-goals.index')
            ->with('success', 'Obiettivo eliminato con successo.');
    }

    /**
     * Verifica che l'utente possa accedere all'obiettivo.
     */
    private function authorizeFinancialGoal(FinancialGoal $financialGoal): void
    {
        $user = Auth::user();

        if ($financialGoal->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questo obiettivo.');
        }
    }
}
