<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectInterHouseholdTransferRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato a fare questa richiesta
     */
    public function authorize(): bool
    {
        return true; // L'autorizzazione è gestita dalla policy
    }

    /**
     * Regole di validazione
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Messaggi di validazione personalizzati
     */
    public function messages(): array
    {
        return [
            'rejection_reason.max' => 'Il motivo del rifiuto non può superare 500 caratteri.',
        ];
    }
}
