<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvestmentPacRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $householdId = $this->user()?->active_household_id;

        return [
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('household_id', $householdId)
                    ->where('active', true)),
            ],
            'investment_asset_id' => ['required', 'integer', 'exists:investment_assets,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'fees' => ['nullable', 'numeric', 'min:0'],
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
            'status' => ['required', 'in:active,paused'],
        ];
    }

    public function messages(): array
    {
        return [
            'inflation_rate_annual.required' => 'Indica la percentuale annua di rivalutazione quando l\'adeguamento inflazione è attivo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'account_id' => $this->filled('account_id') ? $this->input('account_id') : null,
            'end_date' => $this->filled('end_date') ? $this->input('end_date') : null,
            'notes' => $this->filled('notes') ? $this->input('notes') : null,
            'fees' => $this->filled('fees') ? $this->input('fees') : null,
        ]);
    }
}
