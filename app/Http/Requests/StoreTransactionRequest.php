<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesExpenseAccountEligibility;
use App\Models\Account;
use App\Models\Category;
use App\Models\MealVoucherLot;
use App\Services\MealVoucherLedgerService;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransactionRequest extends FormRequest
{
    use ValidatesExpenseAccountEligibility;

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
    protected function prepareForValidation(): void
    {
        $splits = $this->input('splits');

        if (! is_array($splits) || $splits === []) {
            $this->merge(['splits' => null]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateExpenseAccountEligibility($validator);
            $this->validateMealVoucherRules($validator);
        });
    }

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

        $hasSplits = $this->hasSplitPayment();

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
            'meal_voucher_lines' => ['nullable', 'array'],
            'meal_voucher_lines.*.lot_id' => ['required', 'integer', 'exists:meal_voucher_lots,id'],
            'meal_voucher_lines.*.quantity' => ['required', 'integer', 'min:1'],
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
            'splits.min' => 'Servono almeno due conti per un pagamento diviso.',
        ];
    }

    protected function hasSplitPayment(): bool
    {
        return is_array($this->input('splits')) && count($this->input('splits')) >= 2;
    }

    private function validateMealVoucherRules(Validator $validator): void
    {
        if ($this->hasSplitPayment()) {
            $splitAccountIds = collect($this->input('splits', []))->pluck('account_id')->filter()->all();
            if ($splitAccountIds !== [] && Account::query()->whereIn('id', $splitAccountIds)->where('type', Account::MEAL_VOUCHER_TYPE)->exists()) {
                $validator->errors()->add('splits', 'I conti buoni pasto non supportano i pagamenti divisi.');
            }

            return;
        }

        $accountId = $this->input('account_id');
        if (! $accountId) {
            return;
        }

        $account = Account::query()->find($accountId);
        if (! $account || ! $account->isMealVoucher()) {
            return;
        }

        $category = Category::query()->find($this->input('category_id'));
        if (! $category) {
            return;
        }

        $ledger = app(MealVoucherLedgerService::class);
        $amount = abs((float) $this->input('amount', 0));
        $date = Carbon::parse($this->input('date', now()->toDateString()));

        if ($category->type === 'expense') {
            $lines = $this->input('meal_voucher_lines', []);
            if (! is_array($lines) || $lines === []) {
                $validator->errors()->add(
                    'meal_voucher_lines',
                    'Per spendere buoni pasto seleziona i ticket (interi) da usare.'
                );

                return;
            }

            try {
                $computed = $ledger->euroFromLines($account, array_map(fn ($line) => [
                    'lot_id' => (int) ($line['lot_id'] ?? 0),
                    'quantity' => (int) ($line['quantity'] ?? 0),
                ], $lines));

                foreach ($lines as $line) {
                    $lot = MealVoucherLot::query()
                        ->where('account_id', $account->id)
                        ->whereKey((int) ($line['lot_id'] ?? 0))
                        ->first();
                    if (! $lot || (int) ($line['quantity'] ?? 0) > (int) $lot->quantity_remaining) {
                        $validator->errors()->add('meal_voucher_lines', 'Quantità ticket non disponibile.');

                        return;
                    }
                }

                if (abs($computed - $amount) > 0.001) {
                    $validator->errors()->add(
                        'amount',
                        'L\'importo deve corrispondere ai ticket selezionati ('.$computed.' €).'
                    );
                }
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('meal_voucher_lines', $e->getMessage());
            }

            return;
        }

        $unit = $ledger->unitValueOn($account, $date);
        if ($unit === null || $unit <= 0) {
            $validator->errors()->add('account_id', 'Nessun valore ticket vigente per questo conto.');

            return;
        }

        if (! $ledger->isMultipleOfUnit($amount, $unit)) {
            $validator->errors()->add(
                'amount',
                'L\'importo deve essere un multiplo del valore ticket vigente ('.number_format($unit, 2, ',', '.').' €). I buoni non sono frazionabili.'
            );
        }
    }
}
