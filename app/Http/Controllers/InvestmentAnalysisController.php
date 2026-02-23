<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentAnalysisRequest;
use App\Models\InvestmentAnalysis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentAnalysisController extends Controller
{
    /**
     * Mostra l'elenco delle analisi di investimento della household attiva.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $analyses = InvestmentAnalysis::where('household_id', $householdId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($analysis) {
                return [
                    'id' => $analysis->id,
                    'name' => $analysis->name,
                    'template_type' => $analysis->template_type,
                    'start_date' => $analysis->start_date?->format('Y-m-d'),
                    'initial_cost' => (float) $analysis->initial_cost,
                    'total_annual_savings' => $analysis->total_annual_savings !== null ? (float) $analysis->total_annual_savings : null,
                    'breakeven_years' => $analysis->breakeven_years !== null ? (float) $analysis->breakeven_years : null,
                    'roi_percentage' => $analysis->roi_percentage !== null ? (float) $analysis->roi_percentage : null,
                    'template_data' => $analysis->template_data,
                    'created_at' => $analysis->created_at->format('Y-m-d'),
                ];
            });

        return Inertia::render('InvestmentAnalyses/Index', [
            'analyses' => $analyses,
        ]);
    }

    /**
     * Salva una nuova analisi di investimento.
     */
    public function store(StoreInvestmentAnalysisRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        InvestmentAnalysis::create([
            'user_id' => $user->id,
            'household_id' => $householdId,
            ...$request->validated(),
        ]);

        return redirect()->route('investment-analyses.index')
            ->with('success', 'Analisi investimento salvata con successo.');
    }

    /**
     * Elimina un'analisi di investimento.
     */
    public function destroy(InvestmentAnalysis $investmentAnalysis): RedirectResponse
    {
        $user = Auth::user();

        if ($investmentAnalysis->household_id !== $user->active_household_id) {
            abort(403);
        }

        $investmentAnalysis->delete();

        return redirect()->route('investment-analyses.index')
            ->with('success', 'Analisi eliminata con successo.');
    }
}
