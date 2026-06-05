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
            'recurring_reminder.channels.*' => ['in:in_app,email,push'],
            'monthly_spending.enabled' => ['required', 'boolean'],
            'monthly_spending.channels' => ['required', 'array'],
            'monthly_spending.channels.*' => ['in:in_app,email,push'],
            'investment_pac_reminder.enabled' => ['required', 'boolean'],
            'investment_pac_reminder.channels' => ['required', 'array'],
            'investment_pac_reminder.channels.*' => ['in:in_app,email,push'],
        ]);

        $user = $request->user();
        $preferences = $user->preferences ?? [];
        $preferences['notifications']['recurring_reminder'] = [
            'enabled' => $validated['recurring_reminder']['enabled'],
            'channels' => array_values(array_unique($validated['recurring_reminder']['channels'])),
        ];
        $preferences['notifications']['monthly_spending'] = [
            'enabled' => $validated['monthly_spending']['enabled'],
            'channels' => array_values(array_unique($validated['monthly_spending']['channels'])),
        ];
        $preferences['notifications']['investment_pac_reminder'] = [
            'enabled' => $validated['investment_pac_reminder']['enabled'],
            'channels' => array_values(array_unique($validated['investment_pac_reminder']['channels'])),
        ];
        $user->preferences = $preferences;
        $user->save();

        return response()->json([
            'recurring_reminder' => $preferences['notifications']['recurring_reminder'],
            'monthly_spending' => $preferences['notifications']['monthly_spending'],
            'investment_pac_reminder' => $preferences['notifications']['investment_pac_reminder'],
        ]);
    }
}
