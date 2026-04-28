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
            'account_id' => ['nullable', 'integer', Rule::in($accessibleAccountIds)],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.date' => ['required', 'date'],
            'rows.*.amount' => ['required', 'numeric'],
            'rows.*.description' => ['nullable', 'string', 'max:1000'],
            'rows.*.notes' => ['nullable', 'string', 'max:1000'],
            'rows.*.account_id' => ['nullable', 'integer', Rule::in(array_merge([0], $accessibleAccountIds))],
            'rows.*.account_name' => ['nullable', 'string', 'max:255'],
            'rows.*.duplicate_action' => ['nullable', 'string', Rule::in(['import', 'ignore', 'replace', 'update'])],
            'rows.*.duplicate_transaction_id' => ['nullable', 'integer'],
            'rows.*.category_name' => ['nullable', 'string', 'max:255'],
            'category_mappings' => ['nullable', 'array'],
            'category_mappings.*.name' => ['required_with:category_mappings', 'string', 'max:255'],
            'category_mappings.*.action' => ['required_with:category_mappings', 'string', Rule::in(['existing', 'create', 'none'])],
            'category_mappings.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category_mappings.*.type' => ['nullable', 'string', Rule::in(['income', 'expense'])],
            'account_mappings' => ['nullable', 'array'],
            'account_mappings.*.name' => ['required_with:account_mappings', 'string', 'max:255'],
            'account_mappings.*.action' => ['required_with:account_mappings', 'string', Rule::in(['existing', 'create'])],
            'account_mappings.*.account_id' => ['nullable', 'integer', Rule::in(array_merge([0], $accessibleAccountIds))],
            'account_mappings.*.currency_code' => ['nullable', 'string', 'max:10'],
            'account_mappings.*.type' => ['nullable', 'string', Rule::in(['bank', 'cash', 'card', 'broker', 'crypto', 'other'])],
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
