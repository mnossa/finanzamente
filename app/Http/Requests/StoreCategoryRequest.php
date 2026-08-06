<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['income', 'expense'])],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:10'],
            'is_fixed_expense' => ['sometimes', 'boolean'],
            'exclude_from_lifestyle_score' => ['sometimes', 'boolean'],
            'expense_distribution' => ['nullable', Rule::in(['needs', 'wants', 'investments'])],
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
            'name.required' => 'Il nome della categoria è obbligatorio.',
            'name.max' => 'Il nome non può superare i 100 caratteri.',
            'type.required' => 'Seleziona il tipo di categoria.',
            'type.in' => 'Il tipo deve essere entrata o uscita.',
            'color.max' => 'Il colore non può superare i 20 caratteri.',
            'icon.max' => "L'icona non può superare i 10 caratteri.",
        ];
    }
}
