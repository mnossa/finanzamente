<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreDebtCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->active_household_id !== null;
    }

    public function rules(): array
    {
        return [
            'counterparty' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['required', 'exists:currencies,code'],
            'type' => ['required', 'in:debt,credit'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'counterparty.required' => 'La controparte è obbligatoria.',
            'counterparty.max' => 'La controparte non può superare i 100 caratteri.',
            'amount.required' => "L'importo è obbligatorio.",
            'amount.numeric' => "L'importo deve essere un numero.",
            'amount.min' => "L'importo deve essere almeno 0.01.",
            'currency_code.required' => 'La valuta è obbligatoria.',
            'currency_code.exists' => 'La valuta selezionata non esiste.',
            'type.required' => 'Il tipo è obbligatorio.',
            'type.in' => 'Il tipo deve essere debito o credito.',
            'due_date.date' => 'La data di scadenza non è valida.',
            'description.max' => 'La descrizione non può superare i 255 caratteri.',
        ];
    }
}
