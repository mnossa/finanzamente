<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreImportTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

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
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.date' => ['required', 'date'],
            'rows.*.amount' => ['required', 'numeric'],
            'rows.*.description' => ['required', 'string', 'max:1000'],
            'rows.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.required' => 'Seleziona un conto su cui importare le transazioni.',
            'account_id.in' => 'Il conto selezionato non è valido.',
            'rows.required' => 'Nessuna transazione da importare.',
            'rows.min' => 'Seleziona almeno una transazione da importare.',
            'rows.*.date.required' => 'La data è obbligatoria per ogni transazione.',
            'rows.*.date.date' => 'La data non è valida.',
            'rows.*.amount.required' => "L'importo è obbligatorio per ogni transazione.",
            'rows.*.amount.numeric' => "L'importo deve essere un numero.",
            'rows.*.description.required' => 'La descrizione è obbligatoria per ogni transazione.',
            'rows.*.description.max' => 'La descrizione non può superare 1000 caratteri.',
        ];
    }
}
