<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvestmentAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'template_type' => ['required', 'string', 'in:fotovoltaico,auto_elettrica,cappotto_termico,pompa_calore,personalizzato'],
            'start_date' => ['nullable', 'date'],
            'initial_cost' => ['required', 'numeric', 'min:0'],
            'recurring_costs' => ['nullable', 'array'],
            'savings' => ['nullable', 'array'],
            'incentives' => ['nullable', 'array'],
            'template_data' => ['nullable', 'array'],
            'total_annual_savings' => ['nullable', 'numeric', 'min:0'],
            'breakeven_years' => ['nullable', 'numeric', 'min:0'],
            'roi_percentage' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Il nome è obbligatorio.',
            'name.max' => 'Il nome non può superare i 255 caratteri.',
            'template_type.required' => 'Il tipo di template è obbligatorio.',
            'template_type.in' => 'Il tipo di template selezionato non è valido.',
            'initial_cost.required' => 'Il costo iniziale è obbligatorio.',
            'initial_cost.min' => 'Il costo iniziale non può essere negativo.',
        ];
    }
}
