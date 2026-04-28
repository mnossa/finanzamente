<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDashboardLayoutRequest;
use App\Models\DashboardLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardLayoutController extends Controller
{
    /**
     * Restituisce la configurazione layout della dashboard per l'utente corrente.
     * Se non esiste, restituisce la configurazione di default.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        $layout = DashboardLayout::where('user_id', $user->id)
            ->where('household_id', $user->active_household_id)
            ->first();

        return response()->json([
            'config' => $layout ? $layout->config : DashboardLayout::defaultConfig(),
        ]);
    }

    /**
     * Salva (o aggiorna) la configurazione layout per l'utente corrente.
     */
    public function store(StoreDashboardLayoutRequest $request): JsonResponse
    {
        $user = Auth::user();

        $layout = DashboardLayout::updateOrCreate(
            [
                'user_id' => $user->id,
                'household_id' => $user->active_household_id,
            ],
            [
                'config' => $request->validated()['config'],
            ]
        );

        return response()->json([
            'config' => $layout->config,
            'message' => 'Layout salvato con successo.',
        ]);
    }

    /**
     * Reimposta la configurazione layout al valore di default.
     */
    public function reset(): JsonResponse
    {
        $user = Auth::user();

        DashboardLayout::where('user_id', $user->id)
            ->where('household_id', $user->active_household_id)
            ->delete();

        return response()->json([
            'config' => DashboardLayout::defaultConfig(),
            'message' => 'Layout reimpostato al valore predefinito.',
        ]);
    }
}
