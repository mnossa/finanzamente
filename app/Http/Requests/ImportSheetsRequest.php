<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request per ottenere la lista dei fogli (sheets) da un file XLSX.
 * Accetta sia un file locale sia un file da Google Drive.
 */
class ImportSheetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isGoogleDrive = $this->filled('google_drive_file_id');

        return [
            'csv_file' => [$isGoogleDrive ? 'nullable' : 'required', 'file', 'mimes:xlsx', 'max:5120'],
            'google_drive_file_id' => ['nullable', 'string', 'max:200'],
            'google_drive_access_token' => ['nullable', 'required_with:google_drive_file_id', 'string', 'max:2048'],
            'google_drive_mime_type' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Seleziona un file XLSX oppure collega Google Drive.',
            'csv_file.mimes' => 'La rilevazione dei fogli è disponibile solo per file XLSX.',
            'csv_file.max' => 'Il file non può superare 5 MB.',
            'google_drive_access_token.required_with' => 'Il token di accesso Google è obbligatorio per i file da Google Drive.',
        ];
    }
}
