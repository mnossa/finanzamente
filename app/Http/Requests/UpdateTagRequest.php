<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->active_household_id !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il nome del tag è obbligatorio.',
            'name.max' => 'Il nome non può superare i 50 caratteri.',
            'color.max' => 'Il colore non può superare i 20 caratteri.',
        ];
    }
}
