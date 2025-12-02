<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
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
            'source_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(function ($query) use ($householdId, $user) {
                    $query->where('household_id', $householdId)
                        ->where('active', true)
                        ->where(function ($q) use ($user) {
                            $q->where('is_private', false)
                                ->orWhere('owner_user_id', $user->id);
                        });
                }),
            ],
            'destination_account_id' => [
                'required',
                'integer',
                'different:source_account_id',
                Rule::exists('accounts', 'id')->where(function ($query) use ($householdId, $user) {
                    $query->where('household_id', $householdId)
                        ->where('active', true)
                        ->where(function ($q) use ($user) {
                            $q->where('is_private', false)
                                ->orWhere('owner_user_id', $user->id);
                        });
                }),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.00000001'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
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
            'source_account_id.required' => 'Seleziona il conto di origine.',
            'source_account_id.exists' => 'Il conto di origine non è valido o non hai accesso.',
            'destination_account_id.required' => 'Seleziona il conto di destinazione.',
            'destination_account_id.exists' => 'Il conto di destinazione non è valido o non hai accesso.',
            'destination_account_id.different' => 'Il conto di destinazione deve essere diverso da quello di origine.',
            'amount.required' => "L'importo è obbligatorio.",
            'amount.min' => "L'importo deve essere almeno 0,01.",
            'amount.max' => "L'importo non può superare 999.999.999,99.",
            'exchange_rate.min' => 'Il tasso di cambio deve essere maggiore di zero.',
            'fee.min' => 'La commissione non può essere negativa.',
            'fee.max' => 'La commissione non può superare 999.999,99.',
            'date.date' => 'La data non è valida.',
            'description.max' => 'La descrizione non può superare i 500 caratteri.',
        ];
    }
}
