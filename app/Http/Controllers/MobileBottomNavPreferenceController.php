<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileBottomNavPreferenceController extends Controller
{
    /**
     * PATCH /utente/preferenze/nav-mobile (nome rotta: user.preferences.mobile_bottom_nav)
     */
    public function update(Request $request): JsonResponse
    {
        $allowed = config('mobile_bottom_nav.allowed_destinations', []);

        $validated = $request->validate([
            'mobile_bottom_nav' => ['required', 'array', 'size:3'],
            'mobile_bottom_nav.*' => ['required', 'string', Rule::in($allowed), 'distinct'],
        ]);

        $user = $request->user();

        $preferences = $user->preferences ?? [];
        $preferences['mobile_bottom_nav'] = array_values($validated['mobile_bottom_nav']);

        $user->update(['preferences' => $preferences]);

        return response()->json([
            'mobile_bottom_nav' => $preferences['mobile_bottom_nav'],
        ]);
    }
}
