<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'financial_management_type' => ['required', 'string', 'in:debt_balancing,shared_wallet'],
            'balance_type' => ['sometimes', 'string', 'in:equal,custom'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Il nome della household è obbligatorio.',
            'name.max' => 'Il nome della household non può superare i 255 caratteri.',
            'financial_management_type.required' => 'Devi selezionare una modalità di gestione finanziaria.',
            'financial_management_type.in' => 'La modalità di gestione finanziaria selezionata non è valida.',
        ];
    }
}
