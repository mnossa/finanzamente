<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\RecurringTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRecurringTransactionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'day_of_month_mode' => $this->input(
                'day_of_month_mode',
                RecurringTransaction::DAY_OF_MONTH_MODE_START_DATE,
            ),
            'non_working_day_policy' => $this->input(
                'non_working_day_policy',
                RecurringTransaction::NON_WORKING_DAY_POLICY_POSTPONE,
            ),
        ]);
    }

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
            'day_of_month_mode' => ['required', Rule::in(RecurringTransaction::DAY_OF_MONTH_MODES)],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31', 'required_if:day_of_month_mode,'.RecurringTransaction::DAY_OF_MONTH_MODE_FIXED],
            'non_working_day_policy' => ['required', Rule::in(RecurringTransaction::NON_WORKING_DAY_POLICIES)],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                ! in_array($this->input('frequency'), ['monthly', 'yearly'], true)
                && $this->input('day_of_month_mode') !== RecurringTransaction::DAY_OF_MONTH_MODE_START_DATE
            ) {
                $validator->errors()->add(
                    'day_of_month_mode',
                    'Il giorno del mese si può configurare solo per frequenze mensili o annuali.'
                );
            }
        });
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
            'day_of_month_mode.in' => 'La regola giorno selezionata non è valida.',
            'day_of_month.required_if' => 'Indica il giorno fisso della ricorrenza.',
            'day_of_month.integer' => 'Il giorno fisso deve essere un numero.',
            'day_of_month.min' => 'Il giorno fisso deve essere almeno 1.',
            'day_of_month.max' => 'Il giorno fisso non può superare 31.',
            'non_working_day_policy.in' => 'La gestione dei festivi selezionata non è valida.',
            'start_date.required' => 'La data di inizio è obbligatoria.',
            'start_date.date' => 'La data di inizio non è valida.',
            'end_date.date' => 'La data di fine non è valida.',
            'end_date.after_or_equal' => 'La data di fine deve essere successiva o uguale alla data di inizio.',
            'description.max' => 'La descrizione non può superare 1000 caratteri.',
            'debt_credit_id.exists' => 'Il debito/credito selezionato non è valido o è stato già chiuso.',
        ];
    }
}
