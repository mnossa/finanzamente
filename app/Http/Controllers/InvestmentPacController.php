<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvestmentPacRequest;
use App\Models\Account;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvestmentPacController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $pacs = InvestmentPac::with(['asset:id,name,symbol,isin', 'account:id,name'])
            ->where('household_id', $householdId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (InvestmentPac $pac) => [
                'id' => $pac->id,
                'amount' => (float) $pac->amount,
                'adjust_for_inflation' => (bool) $pac->adjust_for_inflation,
                'inflation_rate_annual' => $pac->inflation_rate_annual !== null ? (float) $pac->inflation_rate_annual : null,
                'currency_code' => $pac->currency_code,
                'frequency' => $pac->frequency,
                'start_date' => $pac->start_date?->format('Y-m-d'),
                'end_date' => $pac->end_date?->format('Y-m-d'),
                'last_executed_at' => $pac->last_executed_at?->format('Y-m-d'),
                'status' => $pac->status,
                'notes' => $pac->notes,
                'asset' => [
                    'id' => $pac->asset->id,
                    'name' => $pac->asset->name,
                    'symbol' => $pac->asset->symbol,
                    'isin' => $pac->asset->isin,
                ],
                'account' => $pac->account ? [
                    'id' => $pac->account->id,
                    'name' => $pac->account->name,
                ] : null,
            ]);

        return Inertia::render('InvestmentPacs/Index', ['pacs' => $pacs]);
    }

    public function create(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $accounts = Account::where('household_id', $householdId)->where('active', true)->orderBy('name')->get(['id', 'name', 'currency_code']);
        $assets = InvestmentAsset::orderBy('name')->get(['id', 'name', 'symbol', 'isin', 'currency_code']);

        return Inertia::render('InvestmentPacs/Create', [
            'accounts' => $accounts,
            'assets' => $assets,
        ]);
    }

    public function store(StoreInvestmentPacRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();
        $adjustForInflation = $request->boolean('adjust_for_inflation');

        InvestmentPac::create([
            ...$validated,
            'adjust_for_inflation' => $adjustForInflation,
            'inflation_rate_annual' => $adjustForInflation ? $validated['inflation_rate_annual'] : null,
            'household_id' => $user->active_household_id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return redirect()->route('investment-pacs.index')->with('success', 'PAC creato con successo.');
    }
}
