<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseDistributionController extends Controller
{
    /**
     * Salva le soglie personalizzate per il widget Distribuzione Spese.
     *
     * Le soglie vengono memorizzate nel campo JSON `profile_settings` dell'utente
     * sotto la chiave `expense_distribution_thresholds`.
     * Non è obbligatorio che la somma sia 100 — l'utente può impostare target
     * indipendenti senza che diventino "obblighi" stringenti.
     */
    public function updateThresholds(Request $request)
    {
        $validated = $request->validate([
            'needs' => ['required', 'numeric', 'min:0', 'max:100'],
            'wants' => ['required', 'numeric', 'min:0', 'max:100'],
            'investments' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if (($validated['needs'] + $validated['wants'] + $validated['investments']) > 100) {
            return back()->withErrors(['needs' => 'La somma delle soglie non può superare il 100%.']);
        }

        /** @var User $user */
        $user = Auth::user();
        $settings = $user->profile_settings ?? [];

        $settings['expense_distribution_thresholds'] = [
            'needs' => (float) $validated['needs'],
            'wants' => (float) $validated['wants'],
            'investments' => (float) $validated['investments'],
        ];

        $user->profile_settings = $settings;
        $user->save();

        return back()->with('success', 'Soglie aggiornate con successo.');
    }

    /**
     * Ripristina le soglie al valore di default (50/30/20).
     */
    public function resetThresholds()
    {
        /** @var User $user */
        $user = Auth::user();
        $settings = $user->profile_settings ?? [];

        unset($settings['expense_distribution_thresholds']);

        $user->profile_settings = $settings;
        $user->save();

        return back()->with('success', 'Soglie ripristinate al valore di default.');
    }
}
