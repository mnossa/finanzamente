<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemePreferenceController extends Controller
{
    /**
     * Aggiorna la preferenza tema dell'utente autenticato.
     *
     * PATCH /utente/preferenze/tema (nome rotta: user.preferences.theme)
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'in:light,dark'],
        ]);

        $user = $request->user();

        $preferences = $user->preferences ?? [];
        $preferences['theme'] = $validated['theme'];

        $user->update(['preferences' => $preferences]);

        return response()->json([
            'theme' => $validated['theme'],
        ]);
    }
}
