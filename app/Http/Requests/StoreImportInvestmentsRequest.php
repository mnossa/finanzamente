<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportInvestmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id'               => ['nullable', 'integer', 'exists:accounts,id'],
            'rows'                     => ['required', 'array', 'min:1'],
            'rows.*.buy_date'          => ['required', 'date'],
            'rows.*.quantity'          => ['required', 'numeric', 'gt:0'],
            'rows.*.buy_price'         => ['required', 'numeric', 'min:0'],
            'rows.*.asset_id'          => ['required', 'integer', 'exists:investment_assets,id'],
            'rows.*.fees'              => ['nullable', 'numeric', 'min:0'],
            'rows.*.notes'             => ['nullable', 'string', 'max:1000'],
            'rows.*.is_private'        => ['nullable', 'boolean'],
            'create_cash_transaction'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.required'               => 'Nessuna riga da importare.',
            'rows.min'                    => 'Seleziona almeno una riga da importare.',
            'rows.*.buy_date.required'    => 'La data di acquisto è obbligatoria.',
            'rows.*.buy_date.date'        => 'La data di acquisto non è valida.',
            'rows.*.quantity.required'    => 'La quantità è obbligatoria.',
            'rows.*.quantity.gt'          => 'La quantità deve essere maggiore di zero.',
            'rows.*.buy_price.required'   => 'Il prezzo di acquisto è obbligatorio.',
            'rows.*.buy_price.min'        => 'Il prezzo di acquisto non può essere negativo.',
            'rows.*.asset_id.required'    => "L'asset è obbligatorio.",
            'rows.*.asset_id.exists'      => "L'asset selezionato non esiste.",
            'account_id.exists'           => 'Il conto selezionato non esiste.',
        ];
    }
}
