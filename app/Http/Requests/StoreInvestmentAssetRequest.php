<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentAssetRequest extends FormRequest
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
            'type' => ['required', Rule::in(['crypto', 'etf', 'stock', 'index', 'commodity', 'insurance', 'other'])],
            'symbol' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'currency_code' => ['required', 'string', 'exists:currencies,code'],
            'extra_data' => ['nullable', 'array'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Il tipo di asset è obbligatorio.',
            'type.in' => 'Il tipo di asset non è valido.',
            'name.required' => 'Il nome dell\'asset è obbligatorio.',
            'name.max' => 'Il nome non può superare 255 caratteri.',
            'currency_code.required' => 'La valuta è obbligatoria.',
            'currency_code.exists' => 'La valuta selezionata non è valida.',
        ];
    }
}
