<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMealVoucherUnitValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_value' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_value.required' => 'Il valore di un ticket è obbligatorio.',
            'unit_value.min' => 'Il valore di un ticket deve essere almeno 0,01.',
            'effective_from.required' => 'La data di inizio è obbligatoria.',
            'effective_from.after_or_equal' => 'La data non può essere nel passato.',
        ];
    }
}
