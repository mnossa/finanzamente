<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentPacRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'investment_asset_id' => ['required', 'integer', 'exists:investment_assets,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'adjust_for_inflation' => ['sometimes', 'boolean'],
            'inflation_rate_annual' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                Rule::requiredIf(fn () => $this->boolean('adjust_for_inflation')),
            ],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'frequency' => ['required', 'in:monthly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'inflation_rate_annual.required' => 'Indica la percentuale annua di rivalutazione quando l\'adeguamento inflazione è attivo.',
        ];
    }
}
