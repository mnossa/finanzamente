<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewImportRequest extends FormRequest
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
            'csv_file' => [$isGoogleDrive ? 'nullable' : 'required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
            'google_drive_file_id' => ['nullable', 'string', 'max:200'],
            'google_drive_access_token' => ['nullable', 'required_with:google_drive_file_id', 'string', 'max:2048'],
            'google_drive_mime_type' => ['nullable', 'string', 'max:200'],
            // Opzioni di parsing
            'bank_name' => ['nullable', 'string', 'max:50'],
            'layout_id' => ['nullable', 'integer', 'exists:bank_import_layouts,id'],
            'delimiter' => ['nullable', 'string', 'max:5'],
            'date_format' => ['required', 'string', 'max:50'],
            'has_header' => ['boolean'],
            'encoding' => ['nullable', 'string', Rule::in(['UTF-8', 'ISO-8859-1', 'Windows-1252'])],
            'sheet_index' => ['nullable', 'integer', 'min:0'],
            'column_mapping' => ['required', 'array'],
            'column_mapping.date' => ['required', 'integer', 'min:0'],
            'column_mapping.amount' => ['required', 'integer', 'min:0'],
            'column_mapping.description' => ['required', 'integer', 'min:0'],
            'column_mapping.notes' => ['nullable', 'integer', 'min:0'],
            'column_mapping.category' => ['nullable', 'integer', 'min:0'],
            'column_mapping.account' => ['nullable', 'integer', 'min:0'],
            'column_mapping.currency' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Seleziona un file CSV o XLSX oppure collega Google Drive.',
            'csv_file.file' => 'Il file caricato non è valido.',
            'csv_file.mimes' => 'Il file deve essere in formato CSV, TXT o XLSX.',
            'csv_file.max' => 'Il file non può superare 5 MB.',
            'google_drive_access_token.required_with' => 'Il token di accesso Google è obbligatorio per i file da Google Drive.',
            'bank_name.required' => 'Seleziona una banca.',
            'bank_name.in' => 'La banca selezionata non è valida.',
            'date_format.required' => 'Il formato della data è obbligatorio.',
            'column_mapping.date.required' => 'La colonna della data è obbligatoria.',
            'column_mapping.amount.required' => "La colonna dell'importo è obbligatoria.",
            'column_mapping.description.required' => 'La colonna della descrizione è obbligatoria.',
        ];
    }
}
