<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSimulationScenarioRequest;
use App\Http\Requests\UpdateSimulationScenarioRequest;
use App\Models\SavedSimulationScenario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SimulationScenarioController extends Controller
{
    public function store(StoreSimulationScenarioRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorize('create', SavedSimulationScenario::class);

        $validated = $request->validated();

        $scenario = SavedSimulationScenario::create([
            'household_id' => $user->active_household_id,
            'user_id' => $user->id,
            'name' => $validated['name'],
            'tab' => $validated['tab'],
            'payload' => $validated['payload'],
        ]);

        return redirect()
            ->route('simulations.public')
            ->with('success', "Scenario \"{$scenario->name}\" salvato.")
            ->with('loadedScenarioId', $scenario->id);
    }

    public function update(UpdateSimulationScenarioRequest $request, SavedSimulationScenario $savedSimulationScenario): RedirectResponse
    {
        $this->authorize('update', $savedSimulationScenario);

        $validated = $request->validated();
        $savedSimulationScenario->update($validated);

        return redirect()
            ->route('simulations.public')
            ->with('success', "Scenario \"{$savedSimulationScenario->name}\" aggiornato.")
            ->with('loadedScenarioId', $savedSimulationScenario->id);
    }

    public function destroy(SavedSimulationScenario $savedSimulationScenario): RedirectResponse
    {
        $this->authorize('delete', $savedSimulationScenario);

        $name = $savedSimulationScenario->name;
        $savedSimulationScenario->delete();

        return redirect()
            ->route('simulations.public')
            ->with('success', "Scenario \"{$name}\" eliminato.");
    }

    /**
     * @return array{id: int, name: string, tab: string, payload: array<string, mixed>, updated_at: string|null}
     */
    public static function formatForFrontend(SavedSimulationScenario $scenario): array
    {
        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'tab' => $scenario->tab,
            'payload' => $scenario->payload,
            'updated_at' => $scenario->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, tab: string, updated_at: string|null}>
     */
    public static function listForHousehold(Request $request): array
    {
        $user = $request->user();
        if (! $user || ! $user->active_household_id) {
            return [];
        }

        return SavedSimulationScenario::query()
            ->where('household_id', $user->active_household_id)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'tab', 'updated_at'])
            ->map(fn (SavedSimulationScenario $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'tab' => $s->tab,
                'updated_at' => $s->updated_at?->toIso8601String(),
            ])
            ->all();
    }
}
