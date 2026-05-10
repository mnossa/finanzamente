<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImportLayoutRequest extends FormRequest
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
            'column_mapping.date' => ['required', 'integer', 'min:0'],
            'column_mapping.amount' => ['required', 'integer', 'min:0'],
            'column_mapping.description' => ['required', 'integer', 'min:0'],
            'column_mapping.notes' => ['nullable', 'integer', 'min:0'],
            'column_mapping.category' => ['nullable', 'integer', 'min:0'],
            'column_mapping.currency' => ['nullable', 'integer', 'min:0'],
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
            'column_mapping.date.required' => 'La colonna della data è obbligatoria.',
            'column_mapping.date.integer' => 'La colonna della data deve essere un numero intero.',
            'column_mapping.amount.required' => "La colonna dell'importo è obbligatoria.",
            'column_mapping.amount.integer' => "La colonna dell'importo deve essere un numero intero.",
            'column_mapping.description.required' => 'La colonna della descrizione è obbligatoria.',
            'column_mapping.description.integer' => 'La colonna della descrizione deve essere un numero intero.',
        ];
    }
}
