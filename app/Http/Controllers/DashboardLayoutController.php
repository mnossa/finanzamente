<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDashboardLayoutRequest;
use App\Models\DashboardLayout;
use App\Services\FormulaWidgetLayoutNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardLayoutController extends Controller
{
    /**
     * Restituisce la configurazione layout della board attiva (Home o ?board=).
     */
    public function show(Request $request, FormulaWidgetLayoutNormalizer $normalizer): JsonResponse
    {
        $user = Auth::user();
        $board = $this->resolveBoard($request, $user->id, $user->active_household_id, createHomeIfMissing: true);

        if ($board === null) {
            return response()->json([
                'config' => DashboardLayout::essentialConfig(),
                'board' => null,
                'canEditLayout' => false,
            ]);
        }

        $savedConfig = DashboardLayout::stripUnsupportedWidgets($board->config);

        if ($board->is_home) {
            if (DashboardLayout::isBareEssentialConfig($savedConfig)) {
                $healed = DashboardLayout::essentialConfigForUser($user);
                $board->update(['config' => $healed]);

                return response()->json([
                    'config' => $healed,
                    'board' => [
                        'id' => $board->id,
                        'name' => $board->name,
                        'is_home' => $board->is_home,
                    ],
                    'canEditLayout' => true,
                ]);
            }

            $config = $savedConfig;
        } else {
            $config = $normalizer->mergeInstalledFormulaWidgets($user, $savedConfig);
        }

        $sanitized = $normalizer->sanitizeFormulaWidgets($user, $config);

        if (array_column($board->config['widgets'] ?? [], 'id') !== array_column($sanitized['widgets'] ?? [], 'id')) {
            $board->update(['config' => $sanitized]);
        }

        return response()->json([
            'config' => $normalizer->normalize($user, $sanitized),
            'board' => [
                'id' => $board->id,
                'name' => $board->name,
                'is_home' => $board->is_home,
            ],
            'canEditLayout' => true,
        ]);
    }

    /**
     * Salva il layout della board attiva (Home o custom).
     */
    public function store(StoreDashboardLayoutRequest $request): JsonResponse
    {
        $user = Auth::user();
        $board = $this->resolveBoard($request, $user->id, $user->active_household_id, createHomeIfMissing: true);

        if ($board === null) {
            return response()->json([
                'message' => 'Nessuna dashboard disponibile.',
            ], 404);
        }

        $board->update([
            'config' => $request->validated()['config'],
        ]);

        return response()->json([
            'config' => $board->config,
            'message' => 'Layout salvato con successo.',
        ]);
    }

    /**
     * Reimposta la board al template Essenziale (KPI Home + built-in).
     */
    public function reset(Request $request): JsonResponse
    {
        $user = Auth::user();
        $board = $this->resolveBoard($request, $user->id, $user->active_household_id, createHomeIfMissing: true);

        if ($board === null) {
            return response()->json([
                'message' => 'Nessuna dashboard disponibile.',
                'config' => DashboardLayout::essentialConfig(),
            ], 404);
        }

        $config = DashboardLayout::essentialConfigForUser($user);
        $board->update(['config' => $config]);

        return response()->json([
            'config' => $config,
            'message' => 'Layout reimpostato al valore predefinito.',
        ]);
    }

    private function resolveBoard(
        Request $request,
        int $userId,
        ?int $householdId,
        bool $createHomeIfMissing,
    ): ?DashboardLayout {
        $boardId = $request->integer('board') ?: null;

        if ($boardId) {
            $board = DashboardLayout::findOwned($userId, $householdId, $boardId);
            abort_if($board === null, 404);

            return $board;
        }

        $home = DashboardLayout::findHome($userId, $householdId);

        if ($home !== null) {
            return $home;
        }

        if (! $createHomeIfMissing || $householdId === null) {
            return null;
        }

        $user = Auth::user();

        return DashboardLayout::create([
            'user_id' => $userId,
            'household_id' => $householdId,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfigForUser($user),
        ]);
    }
}
