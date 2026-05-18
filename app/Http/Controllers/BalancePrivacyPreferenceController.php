<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalancePrivacyPreferenceController extends Controller
{
    /**
     * PATCH /utente/preferenze/saldi (nome rotta: user.preferences.hide_balances)
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hide_balances' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $preferences = $user->preferences ?? [];
        $preferences['hide_balances'] = $validated['hide_balances'];
        $user->preferences = $preferences;
        $user->save();

        return response()->json([
            'hide_balances' => $validated['hide_balances'],
        ]);
    }
}
