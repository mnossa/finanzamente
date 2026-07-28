<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $householdId = $this->user()?->active_household_id;

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('household_id', $householdId)->where('active', true)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Inserisci l\'importo della cedola.',
            'amount.min' => 'L\'importo deve essere maggiore di zero.',
            'date.required' => 'Inserisci la data dello stacco.',
        ];
    }
}
