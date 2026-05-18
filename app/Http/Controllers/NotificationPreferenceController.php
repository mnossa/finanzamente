<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    /**
     * PATCH /utente/preferenze/notifiche
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recurring_reminder.enabled' => ['required', 'boolean'],
            'recurring_reminder.channels' => ['required', 'array'],
            'recurring_reminder.channels.*' => ['in:in_app,email'],
        ]);

        $user = $request->user();
        $preferences = $user->preferences ?? [];
        $preferences['notifications']['recurring_reminder'] = [
            'enabled' => $validated['recurring_reminder']['enabled'],
            'channels' => array_values(array_unique($validated['recurring_reminder']['channels'])),
        ];
        $user->preferences = $preferences;
        $user->save();

        return response()->json([
            'recurring_reminder' => $preferences['notifications']['recurring_reminder'],
        ]);
    }
}
