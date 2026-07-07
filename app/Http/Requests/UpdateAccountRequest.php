<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('type') === Account::SAVINGS_DEPOSIT_TYPE) {
            $this->merge([
                'type' => 'bank',
                'is_savings_deposit' => true,
            ]);
        }
    }

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
     * @return array<string, ValidationRule|array<mixed>|string>
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
            'interest_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                Rule::requiredIf(fn () => (bool) $this->input('is_savings_deposit', false)),
            ],
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
            'interest_rate.required' => 'Il tasso di interesse è obbligatorio per i conti deposito.',
            'interest_rate.numeric' => 'Il tasso di interesse deve essere un numero.',
            'interest_rate.min' => 'Il tasso di interesse non può essere negativo.',
            'interest_rate.max' => 'Il tasso di interesse non può superare il 100%.',
        ];
    }
}
