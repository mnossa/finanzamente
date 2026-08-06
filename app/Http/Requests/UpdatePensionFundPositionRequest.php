<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePensionFundPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'position' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'position.required' => 'Inserisci la posizione attuale del fondo.',
            'position.numeric' => 'La posizione deve essere un numero.',
            'position.min' => 'La posizione non può essere negativa.',
            'position.max' => 'La posizione è troppo alta.',
        ];
    }
}
