<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewInvestmentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv_file'                        => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx'],
            'delimiter'                        => ['nullable', 'string', 'max:5'],
            'date_format'                      => ['nullable', 'string', 'max:50'],
            'has_header'                       => ['nullable', 'boolean'],
            'encoding'                         => ['nullable', 'string', Rule::in(['UTF-8', 'ISO-8859-1', 'Windows-1252'])],
            'column_mapping'                   => ['required', 'array'],
            'column_mapping.buy_date'          => ['nullable', 'integer', 'min:0'],
            'column_mapping.quantity'          => ['nullable', 'integer', 'min:0'],
            'column_mapping.buy_price'         => ['nullable', 'integer', 'min:0'],
            'column_mapping.ticker'            => ['nullable', 'integer', 'min:0'],
            'column_mapping.isin'              => ['nullable', 'integer', 'min:0'],
            'column_mapping.fees'              => ['nullable', 'integer', 'min:0'],
            'column_mapping.notes'             => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Il file CSV è obbligatorio.',
            'csv_file.max'      => 'Il file non può superare 5 MB.',
            'csv_file.mimes'    => 'Il file deve essere in formato CSV, TXT o XLSX.',
        ];
    }
}
