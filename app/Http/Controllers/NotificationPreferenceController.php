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
            'upcoming_due_dates.frequency' => ['required', 'in:daily,weekly,never'],
            'upcoming_due_dates.channels' => ['required', 'array'],
            'upcoming_due_dates.channels.*' => ['in:in_app,email,push'],
            'monthly_spending.enabled' => ['required', 'boolean'],
            'monthly_spending.channels' => ['required', 'array'],
            'monthly_spending.channels.*' => ['in:in_app,email,push'],
            'educational_suggestions.enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $preferences = $user->preferences ?? [];
        $frequency = $validated['upcoming_due_dates']['frequency'];
        $dueChannels = array_values(array_unique($validated['upcoming_due_dates']['channels']));
        $dailyEnabled = $frequency === 'daily';

        $preferences['notifications']['upcoming_due_dates'] = [
            'frequency' => $frequency,
            'channels' => $dueChannels,
        ];
        $preferences['notifications']['recurring_reminder'] = [
            'enabled' => $dailyEnabled,
            'channels' => $dueChannels,
        ];
        $preferences['notifications']['investment_pac_reminder'] = [
            'enabled' => $dailyEnabled,
            'channels' => $dueChannels,
        ];
        $preferences['notifications']['monthly_spending'] = [
            'enabled' => $validated['monthly_spending']['enabled'],
            'channels' => array_values(array_unique($validated['monthly_spending']['channels'])),
        ];
        $preferences['notifications']['educational_suggestions'] = [
            'enabled' => $validated['educational_suggestions']['enabled'],
        ];
        $user->preferences = $preferences;
        $user->save();

        return response()->json([
            'upcoming_due_dates' => $preferences['notifications']['upcoming_due_dates'],
            'monthly_spending' => $preferences['notifications']['monthly_spending'],
            'educational_suggestions' => $preferences['notifications']['educational_suggestions'],
        ]);
    }
}
