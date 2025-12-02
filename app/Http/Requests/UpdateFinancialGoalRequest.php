<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFinancialGoalRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'current_amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'currency_code' => ['required', 'exists:currencies,code'],
            'target_date' => ['nullable', 'date'],
            'icon' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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
            'name.required' => 'Il nome è obbligatorio.',
            'name.max' => 'Il nome non può superare 255 caratteri.',
            'description.max' => 'La descrizione non può superare 1000 caratteri.',
            'target_amount.required' => "L'importo target è obbligatorio.",
            'target_amount.numeric' => "L'importo target deve essere un numero.",
            'target_amount.min' => "L'importo target deve essere almeno 0,01.",
            'target_amount.max' => "L'importo target è troppo alto.",
            'current_amount.required' => "L'importo attuale è obbligatorio.",
            'current_amount.numeric' => "L'importo attuale deve essere un numero.",
            'current_amount.min' => "L'importo attuale non può essere negativo.",
            'currency_code.required' => 'La valuta è obbligatoria.',
            'currency_code.exists' => 'La valuta selezionata non è valida.',
            'target_date.date' => 'La data target non è valida.',
            'color.regex' => 'Il colore deve essere in formato esadecimale (#RRGGBB).',
        ];
    }
}
