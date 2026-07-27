<?php

namespace App\Http\Controllers;

use App\Models\Consent;
use App\Services\ProductAnalytics\ProductAnalyticsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAnalyticsIngestController extends Controller
{
    public function __invoke(Request $request, ProductAnalyticsRecorder $recorder): JsonResponse
    {
        if (! config('product_analytics.enabled', true)) {
            return response()->json(['accepted' => 0]);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['accepted' => 0], 401);
        }

        $hasConsent = Consent::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'analytics_tracking')
            ->where('status', 'granted')
            ->exists();

        if (! $hasConsent) {
            return response()->json(['accepted' => 0, 'reason' => 'consent_required']);
        }

        $validated = $request->validate([
            'events' => ['required', 'array', 'max:'.(int) config('product_analytics.max_events_per_request', 20)],
            'events.*.name' => ['required', 'string', 'max:128'],
            'events.*.data' => ['nullable', 'array'],
            'events.*.count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $accepted = $recorder->recordMany($validated['events']);

        return response()->json(['accepted' => $accepted]);
    }
}
