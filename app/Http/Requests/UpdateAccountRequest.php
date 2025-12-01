<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', Rule::in(array_keys(Account::TYPES))],
            'initial_balance' => ['sometimes', 'required', 'numeric', 'min:-999999999.99', 'max:999999999.99'],
            'currency_code' => ['sometimes', 'required', 'string', 'exists:currencies,code'],
            'active' => ['sometimes', 'boolean'],
            'is_private' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Il nome del conto è obbligatorio.',
            'name.max' => 'Il nome del conto non può superare 255 caratteri.',
            'type.required' => 'Il tipo di conto è obbligatorio.',
            'type.in' => 'Il tipo di conto selezionato non è valido.',
            'initial_balance.required' => 'Il saldo iniziale è obbligatorio.',
            'initial_balance.numeric' => 'Il saldo iniziale deve essere un numero.',
            'initial_balance.min' => 'Il saldo iniziale è troppo basso.',
            'initial_balance.max' => 'Il saldo iniziale è troppo alto.',
            'currency_code.required' => 'La valuta è obbligatoria.',
            'currency_code.exists' => 'La valuta selezionata non è valida.',
        ];
    }
}
