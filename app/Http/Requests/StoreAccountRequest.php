<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_keys(Account::TYPES))],
            'initial_balance' => ['required', 'numeric', 'min:-999999999.99', 'max:999999999.99'],
            'currency_code' => ['required', 'string', 'exists:currencies,code'],
            'is_private' => ['boolean'],
            'interest_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                Rule::requiredIf(fn () => (bool) $this->input('is_savings_deposit', false)),
            ],
            'ticket_unit_value' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:999999.99',
                Rule::requiredIf(fn () => $this->input('type') === Account::MEAL_VOUCHER_TYPE),
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
            'ticket_unit_value.required' => 'Il valore di un ticket è obbligatorio per i buoni pasto.',
            'ticket_unit_value.numeric' => 'Il valore di un ticket deve essere un numero.',
            'ticket_unit_value.min' => 'Il valore di un ticket deve essere almeno 0,01.',
            'ticket_unit_value.max' => 'Il valore di un ticket è troppo alto.',
        ];
    }
}
