<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
        $user = Auth::user();
        $householdId = $user->active_household_id;

        // Ottieni gli ID dei conti accessibili dall'utente
        $accessibleAccountIds = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($q) use ($user) {
                $q->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->pluck('id')
            ->toArray();

        $hasSplits = is_array($this->input('splits')) && count($this->input('splits', [])) >= 2;

        return [
            'account_id' => [
                Rule::requiredIf(! $hasSplits),
                'nullable',
                'integer',
                Rule::in($accessibleAccountIds),
            ],
            'splits' => ['nullable', 'array', 'min:2'],
            'splits.*.account_id' => ['required', 'integer', Rule::in($accessibleAccountIds)],
            'splits.*.amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'category_id' => ['required', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_private' => ['boolean'],
            // Multi-currency: l'importo `amount` è sempre nella valuta del conto.
            // I campi sotto sono opzionali e si compilano solo quando l'utente
            // ha pagato in valuta diversa da quella del conto (es. £30 con carta EUR).
            'original_amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999.99'],
            'original_currency_code' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'manual_rate' => ['nullable', 'numeric', 'min:0.0001', 'max:100000'],
            'is_tax_deductible' => ['boolean'],
            'tax_deduction_rate' => ['nullable', 'required_if:is_tax_deductible,true', 'numeric', 'min:0.01', 'max:100'],
            'tax_deduction_type' => ['nullable', 'required_if:is_tax_deductible,true', 'string', 'max:50', Rule::in(['mediche', 'veterinarie', 'istruzione', 'mutuo', 'ristrutturazione', 'assicurazioni', 'previdenza', 'donazioni', 'altro'])],
            'tax_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'new_tag_names' => ['nullable', 'array'],
            'new_tag_names.*' => ['string', 'max:50'],
            'debt_credit_id' => [
                'nullable',
                'integer',
                Rule::exists('debts_credits', 'id')->where(function ($query) use ($householdId) {
                    $query->where('household_id', $householdId)
                        ->whereIn('status', ['open', 'overdue']);
                }),
            ],
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
            'account_id.required' => 'Seleziona un conto.',
            'account_id.in' => 'Il conto selezionato non è valido.',
            'category_id.required' => 'Seleziona una categoria.',
            'category_id.exists' => 'La categoria selezionata non è valida.',
            'amount.required' => "L'importo è obbligatorio.",
            'amount.numeric' => "L'importo deve essere un numero.",
            'amount.min' => "L'importo deve essere almeno 0,01.",
            'amount.max' => "L'importo è troppo alto.",
            'date.required' => 'La data è obbligatoria.',
            'date.date' => 'La data non è valida.',
            'description.max' => 'La descrizione non può superare 1000 caratteri.',
            'tax_deduction_rate.required_if' => 'La percentuale di detrazione è obbligatoria per le spese detraibili.',
            'tax_deduction_rate.min' => 'La percentuale di detrazione deve essere almeno 0,01%.',
            'tax_deduction_rate.max' => 'La percentuale di detrazione non può superare il 100%.',
            'tax_deduction_type.required_if' => 'Il tipo di detrazione è obbligatorio per le spese detraibili.',
            'tax_deduction_type.in' => 'Il tipo di detrazione selezionato non è valido.',
            'tax_year.min' => "L'anno fiscale non è valido.",
            'tax_year.max' => "L'anno fiscale non è valido.",
            'debt_credit_id.exists' => 'Il debito/credito selezionato non è valido o è stato già chiuso.',
        ];
    }
}
