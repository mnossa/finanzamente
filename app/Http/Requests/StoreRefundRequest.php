<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreRefundRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        return [
            'original_transaction_id' => [
                'required',
                'integer',
                Rule::exists('transactions', 'id')->where(function ($query) use ($householdId, $user) {
                    $query->whereNull('deleted_at')
                        ->whereNull('transfer_id')
                        ->whereNull('refund_id')
                        ->whereIn('account_id', function ($subquery) use ($householdId, $user) {
                            $subquery->select('id')
                                ->from('accounts')
                                ->where('household_id', $householdId)
                                ->whereNull('deleted_at')
                                ->where(function ($q) use ($user) {
                                    $q->where('is_private', false)
                                        ->orWhere('owner_user_id', $user->id);
                                });
                        });
                }),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(function ($query) use ($householdId) {
                    $query->where('type', 'income')
                        ->where(function ($q) use ($householdId) {
                            $q->where('household_id', $householdId)
                                ->orWhereNull('household_id');
                        });
                }),
            ],
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
            'original_transaction_id.required' => 'Seleziona la transazione da rimborsare.',
            'original_transaction_id.exists' => 'La transazione selezionata non è valida o non hai accesso.',
            'amount.required' => "L'importo del rimborso è obbligatorio.",
            'amount.min' => "L'importo deve essere almeno 0,01.",
            'amount.max' => "L'importo non può superare 999.999.999,99.",
            'category_id.required' => 'Seleziona una categoria per il rimborso.',
            'category_id.exists' => 'La categoria selezionata non è valida.',
            'date.date' => 'La data non è valida.',
            'description.max' => 'La descrizione non può superare i 500 caratteri.',
        ];
    }
}
