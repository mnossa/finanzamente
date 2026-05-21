<?php

namespace App\Http\Requests;

use App\Models\SavedSimulationScenario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSimulationScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->user()?->active_household_id;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('saved_simulation_scenarios', 'name')
                    ->where('household_id', $householdId)
                    ->where('user_id', $this->user()?->id),
            ],
            'tab' => ['required', 'string', Rule::in(SavedSimulationScenario::TABS)],
            'payload' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Esiste già uno scenario salvato con questo nome.',
            'tab.in' => 'La scheda della simulazione non è valida.',
        ];
    }
}
