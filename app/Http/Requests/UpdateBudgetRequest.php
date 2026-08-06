<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->active_household_id !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['required', 'exists:currencies,code'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'La categoria è obbligatoria.',
            'category_id.exists' => 'La categoria selezionata non esiste.',
            'amount.required' => "L'importo è obbligatorio.",
            'amount.numeric' => "L'importo deve essere un numero.",
            'amount.min' => "L'importo deve essere almeno 0.01.",
            'currency_code.required' => 'La valuta è obbligatoria.',
            'currency_code.exists' => 'La valuta selezionata non esiste.',
            'period_start.required' => 'La data di inizio è obbligatoria.',
            'period_start.date' => 'La data di inizio non è valida.',
            'period_end.required' => 'La data di fine è obbligatoria.',
            'period_end.date' => 'La data di fine non è valida.',
            'period_end.after_or_equal' => 'La data di fine deve essere uguale o successiva alla data di inizio.',
            'description.max' => 'La descrizione non può superare i 255 caratteri.',
        ];
    }
}
