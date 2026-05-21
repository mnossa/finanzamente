<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSimulationScenarioRequest extends FormRequest
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
        $scenario = $this->route('saved_simulation_scenario');
        $householdId = $this->user()?->active_household_id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('saved_simulation_scenarios', 'name')
                    ->where('household_id', $householdId)
                    ->where('user_id', $this->user()?->id)
                    ->ignore($scenario?->id),
            ],
            'payload' => ['sometimes', 'required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Esiste già uno scenario salvato con questo nome.',
        ];
    }
}
