<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInterHouseholdTransferRequest extends FormRequest
{
    /**
     * Determina se l'utente è autorizzato a fare questa richiesta
     */
    public function authorize(): bool
    {
        return true; // L'autorizzazione è gestita dalla policy
    }

    /**
     * Regole di validazione
     */
    public function rules(): array
    {
        return [
            'source_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->whereNull('deleted_at'),
            ],
            'dest_account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->whereNull('deleted_at'),
                'different:source_account_id',
            ],
            'dest_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'source_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999999.99',
            ],
            'dest_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:999999999.99',
            ],
            'source_currency' => [
                'nullable',
                'string',
                'size:3',
                Rule::exists('currencies', 'code'),
            ],
            'dest_currency' => [
                'nullable',
                'string',
                'size:3',
                Rule::exists('currencies', 'code'),
            ],
            'exchange_rate' => [
                'nullable',
                'numeric',
                'min:0.00000001',
                'max:999999.99999999',
            ],
            'fee' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'transfer_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],
            'exclude_from_stats' => [
                'boolean',
            ],
        ];
    }

    /**
     * Messaggi di validazione personalizzati
     */
    public function messages(): array
    {
        return [
            'source_account_id.required' => 'Seleziona l\'account sorgente.',
            'source_account_id.exists' => 'L\'account sorgente selezionato non è valido.',
            'dest_account_id.required' => 'Seleziona l\'account destinatario.',
            'dest_account_id.exists' => 'L\'account destinatario selezionato non è valido.',
            'dest_account_id.different' => 'L\'account destinatario deve essere diverso dall\'account sorgente.',
            'source_amount.required' => 'Inserisci l\'importo del trasferimento.',
            'source_amount.numeric' => 'L\'importo deve essere un numero.',
            'source_amount.min' => 'L\'importo deve essere almeno 0,01.',
            'source_amount.max' => 'L\'importo non può superare 999.999.999,99.',
            'dest_amount.numeric' => 'L\'importo di destinazione deve essere un numero.',
            'dest_amount.min' => 'L\'importo di destinazione deve essere almeno 0,01.',
            'dest_amount.max' => 'L\'importo di destinazione non può superare 999.999.999,99.',
            'exchange_rate.numeric' => 'Il tasso di cambio deve essere un numero.',
            'exchange_rate.min' => 'Il tasso di cambio deve essere maggiore di 0.',
            'fee.numeric' => 'La commissione deve essere un numero.',
            'fee.min' => 'La commissione non può essere negativa.',
            'fee.max' => 'La commissione non può superare 999.999,99.',
            'description.max' => 'La descrizione non può superare 500 caratteri.',
            'notes.max' => 'Le note non possono superare 1000 caratteri.',
            'transfer_date.date' => 'La data del trasferimento non è valida.',
            'transfer_date.before_or_equal' => 'La data del trasferimento non può essere futura.',
        ];
    }

    /**
     * Validazione aggiuntiva dopo le regole standard
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Verifica che gli account appartengano a households diverse
            $sourceAccountId = $this->input('source_account_id');
            $destAccountId = $this->input('dest_account_id');

            if ($sourceAccountId && $destAccountId) {
                $sourceAccount = Account::find($sourceAccountId);
                $destAccount = Account::find($destAccountId);

                if ($sourceAccount && $destAccount && $sourceAccount->household_id === $destAccount->household_id) {
                    $validator->errors()->add(
                        'dest_account_id',
                        'Gli account devono appartenere a households diverse. Usa i trasferimenti normali per account della stessa household.'
                    );
                }
            }

            // Verifica che l'utente appartenga alla household sorgente
            if ($sourceAccountId) {
                $sourceAccount = Account::with('household.users')->find($sourceAccountId);
                if ($sourceAccount && ! $sourceAccount->household->users()->where('users.id', $this->user()->id)->exists()) {
                    $validator->errors()->add(
                        'source_account_id',
                        'Non hai accesso all\'account sorgente selezionato.'
                    );
                }
            }

            // Se dest_user_id è specificato, verifica che appartenga alla household destinataria
            $destUserId = $this->input('dest_user_id');
            if ($destUserId && $destAccountId) {
                $destAccount = Account::with('household.users')->find($destAccountId);
                if ($destAccount && ! $destAccount->household->users()->where('users.id', $destUserId)->exists()) {
                    $validator->errors()->add(
                        'dest_user_id',
                        'L\'utente destinatario deve appartenere alla household dell\'account destinatario.'
                    );
                }
            }
        });
    }
}
