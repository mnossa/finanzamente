<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreRecurringTransactionRequest extends FormRequest
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

        return [
            'account_id' => ['required', 'integer', Rule::in($accessibleAccountIds)],
            'category_id' => ['required', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
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
            'frequency.required' => 'La frequenza è obbligatoria.',
            'frequency.in' => 'La frequenza selezionata non è valida.',
            'start_date.required' => 'La data di inizio è obbligatoria.',
            'start_date.date' => 'La data di inizio non è valida.',
            'end_date.date' => 'La data di fine non è valida.',
            'end_date.after_or_equal' => 'La data di fine deve essere successiva o uguale alla data di inizio.',
            'description.max' => 'La descrizione non può superare 1000 caratteri.',
            'debt_credit_id.exists' => 'Il debito/credito selezionato non è valido o è stato già chiuso.',
        ];
    }
}
