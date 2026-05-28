<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'investment_asset_id' => ['required', 'integer', 'exists:investment_assets,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'frequency' => ['required', 'in:monthly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        InvestmentPac::create([
            ...$validated,
            'household_id' => $user->active_household_id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return redirect()->route('investment-pacs.index')->with('success', 'PAC creato con successo.');
    }
}
