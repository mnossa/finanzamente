<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveDuplicateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_to_remove' => ['required', Rule::in(['primary', 'candidate'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_to_remove.required' => 'Indica quale movimento eliminare.',
            'transaction_to_remove.in' => 'Movimento da eliminare non valido.',
        ];
    }
}
