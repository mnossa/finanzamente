<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentImportLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:10'],
            'delimiter' => ['required', 'string', 'max:5'],
            'date_format' => ['required', 'string', 'max:50'],
            'has_header' => ['boolean'],
            'encoding' => ['required', 'string', Rule::in(['UTF-8', 'ISO-8859-1', 'Windows-1252'])],
            'column_mapping' => ['required', 'array'],
            'column_mapping.buy_date' => ['required', 'integer', 'min:0'],
            'column_mapping.quantity' => ['required', 'integer', 'min:0'],
            'column_mapping.buy_price' => ['required', 'integer', 'min:0'],
            'column_mapping.ticker' => ['nullable', 'integer', 'min:0'],
            'column_mapping.isin' => ['nullable', 'integer', 'min:0'],
            'column_mapping.fees' => ['nullable', 'integer', 'min:0'],
            'column_mapping.notes' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il nome del layout è obbligatorio.',
            'name.max' => 'Il nome del layout non può superare 100 caratteri.',
            'delimiter.required' => 'Il separatore CSV è obbligatorio.',
            'date_format.required' => 'Il formato della data è obbligatorio.',
            'encoding.required' => 'La codifica del file è obbligatoria.',
            'encoding.in' => 'La codifica selezionata non è supportata.',
            'column_mapping.required' => 'La mappatura delle colonne è obbligatoria.',
            'column_mapping.buy_date.required' => 'La colonna della data di acquisto è obbligatoria.',
            'column_mapping.quantity.required' => 'La colonna della quantità è obbligatoria.',
            'column_mapping.buy_price.required' => 'La colonna del prezzo è obbligatoria.',
        ];
    }
}
