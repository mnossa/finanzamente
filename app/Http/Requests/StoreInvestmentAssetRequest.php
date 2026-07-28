<?php

namespace App\Http\Requests;

use App\Models\InvestmentAsset;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestmentAssetRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(InvestmentAsset::TYPES))],
            'allocation_asset_class' => ['nullable', Rule::in(['equities', 'bonds', 'commodities', 'crypto', 'other'])],
            'symbol' => ['nullable', 'string', 'max:20'],
            'isin' => ['nullable', 'string', 'max:12', 'regex:/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/'],
            'exchange' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'currency_code' => ['required', 'string', 'exists:currencies,code'],
            'extra_data' => ['nullable', 'array'],
            'income_policy' => ['nullable', 'string', Rule::in(array_keys(InvestmentAsset::INCOME_POLICIES))],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Il tipo di asset è obbligatorio.',
            'type.in' => 'Il tipo di asset non è valido.',
            'symbol.max' => 'Il simbolo non può superare 20 caratteri.',
            'isin.max' => 'Il codice ISIN non può superare 12 caratteri.',
            'isin.regex' => 'Il formato ISIN non è valido (es. US0378331005).',
            'exchange.max' => 'La borsa non può superare 50 caratteri.',
            'name.required' => 'Il nome dell\'asset è obbligatorio.',
            'name.max' => 'Il nome non può superare 255 caratteri.',
            'currency_code.required' => 'La valuta è obbligatoria.',
            'currency_code.exists' => 'La valuta selezionata non è valida.',
            'income_policy.in' => 'Seleziona Accumulo o Distribuzione.',
        ];
    }
}
