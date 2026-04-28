<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateRefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->active_household_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_private' => ['nullable', 'boolean'],
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
            'amount.required' => "L'importo del rimborso è obbligatorio.",
            'amount.min' => "L'importo deve essere almeno 0,01.",
            'amount.max' => "L'importo non può superare 999.999.999,99.",
            'date.date' => 'La data non è valida.',
            'description.max' => 'La descrizione non può superare i 500 caratteri.',
        ];
    }
}
