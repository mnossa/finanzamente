<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentRequest extends FormRequest
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
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'asset_id' => ['required', 'integer', 'exists:investment_assets,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'buy_price' => ['required', 'numeric', 'min:0'],
            'buy_date' => ['required', 'date'],
            'fees' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_private' => ['nullable', 'boolean'],
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
            'asset_id.required' => 'L\'asset è obbligatorio.',
            'asset_id.exists' => 'L\'asset selezionato non esiste.',
            'quantity.required' => 'La quantità è obbligatoria.',
            'quantity.gt' => 'La quantità deve essere maggiore di zero.',
            'buy_price.required' => 'Il prezzo di acquisto è obbligatorio.',
            'buy_price.min' => 'Il prezzo di acquisto non può essere negativo.',
            'buy_date.required' => 'La data di acquisto è obbligatoria.',
            'buy_date.date' => 'La data di acquisto non è valida.',
            'fees.min' => 'Le commissioni non possono essere negative.',
        ];
    }
}
