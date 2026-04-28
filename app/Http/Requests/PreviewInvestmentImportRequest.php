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
        $isGoogleDrive = $this->filled('google_drive_file_id');

        return [
            // File locale oppure Google Drive (almeno uno dei due)
            'csv_file' => [$isGoogleDrive ? 'nullable' : 'required', 'file', 'max:5120', 'mimes:csv,txt,xlsx'],
            'google_drive_file_id' => ['nullable', 'string', 'max:200'],
            'google_drive_access_token' => ['nullable', 'required_with:google_drive_file_id', 'string', 'max:2048'],
            'google_drive_mime_type' => ['nullable', 'string', 'max:200'],
            // Opzioni di parsing
            'delimiter' => ['nullable', 'string', 'max:5'],
            'date_format' => ['nullable', 'string', 'max:50'],
            'has_header' => ['nullable', 'boolean'],
            'encoding' => ['nullable', 'string', Rule::in(['UTF-8', 'ISO-8859-1', 'Windows-1252'])],
            'sheet_index' => ['nullable', 'integer', 'min:0'],
            'column_mapping' => ['required', 'array'],
            'column_mapping.buy_date' => ['nullable', 'integer', 'min:0'],
            'column_mapping.quantity' => ['nullable', 'integer', 'min:0'],
            'column_mapping.buy_price' => ['nullable', 'integer', 'min:0'],
            'column_mapping.ticker' => ['nullable', 'integer', 'min:0'],
            'column_mapping.isin' => ['nullable', 'integer', 'min:0'],
            'column_mapping.fees' => ['nullable', 'integer', 'min:0'],
            'column_mapping.notes' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Seleziona un file CSV o XLSX oppure collega Google Drive.',
            'csv_file.max' => 'Il file non può superare 5 MB.',
            'csv_file.mimes' => 'Il file deve essere in formato CSV, TXT o XLSX.',
            'google_drive_access_token.required_with' => 'Il token di accesso Google è obbligatorio per i file da Google Drive.',
        ];
    }
}
