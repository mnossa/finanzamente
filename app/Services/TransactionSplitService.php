<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionSplitService
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
        private readonly MealVoucherLedgerService $mealVoucherLedger,
    ) {}

    /**
     * @param  array<int, array{account_id: int, amount: float}>  $lines
     * @return Collection<int, Transaction>
     */
    public function createSplit(User $user, array $header, array $lines, Category $category): Collection
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'splits' => ['Servono almeno due conti per un pagamento diviso.'],
            ]);
        }

        $sign = $category->type === 'expense' ? -1 : 1;
        $splitGroupId = (string) Str::uuid();
        $date = Carbon::parse($header['date']);
        $accountIds = collect($lines)->pluck('account_id')->unique()->values()->all();

        $accounts = Account::whereIn('id', $accountIds)
            ->where('household_id', $user->active_household_id)
            ->get()
            ->keyBy('id');

        if ($accounts->count() !== count($accountIds)) {
            throw ValidationException::withMessages([
                'splits' => ['Uno o più conti non sono validi per il nucleo attivo.'],
            ]);
        }

        $currencies = $accounts->pluck('currency_code')->unique();
        if ($currencies->count() > 1) {
            throw ValidationException::withMessages([
                'splits' => ['I conti del pagamento diviso devono avere la stessa valuta.'],
            ]);
        }

        $totalAbs = collect($lines)->sum(fn (array $line) => abs((float) $line['amount']));
        $headerAmount = abs((float) $header['amount']);
        if (abs($totalAbs - $headerAmount) > 0.02) {
            throw ValidationException::withMessages([
                'splits' => ['La somma delle righe deve corrispondere all\'importo totale.'],
            ]);
        }

        $mealVoucherLines = array_map(fn ($line) => [
            'lot_id' => (int) ($line['lot_id'] ?? 0),
            'quantity' => (int) ($line['quantity'] ?? 0),
        ], $header['meal_voucher_lines'] ?? []);

        return DB::transaction(function () use ($user, $header, $lines, $category, $sign, $splitGroupId, $date, $accounts, $mealVoucherLines) {
            $transactions = collect();
            $tagIds = $header['tag_ids'] ?? [];

            foreach ($lines as $index => $line) {
                $account = $accounts[$line['account_id']];
                $amount = $sign * abs((float) $line['amount']);

                $currencyFields = $this->buildCurrencyFields($header, $account, $date, $amount);

                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'account_id' => $account->id,
                    'category_id' => $category->id,
                    'amount' => $amount,
                    'date' => $date->toDateString(),
                    'description' => $header['description'] ?? null,
                    'is_private' => $header['is_private'] ?? false,
                    'debt_credit_id' => $header['debt_credit_id'] ?? null,
                    'split_group_id' => $splitGroupId,
                    'is_split_primary' => $index === 0,
                    'is_tax_deductible' => $header['is_tax_deductible'] ?? false,
                    'tax_deduction_rate' => $header['tax_deduction_rate'] ?? null,
                    'tax_deduction_type' => $header['tax_deduction_type'] ?? null,
                    'tax_year' => $header['tax_year'] ?? null,
                    ...$currencyFields,
                ]);

                if (! empty($tagIds)) {
                    $transaction->tags()->sync($tagIds);
                }

                if ($account->isMealVoucher()) {
                    if ($amount < 0) {
                        $this->mealVoucherLedger->applySpend($account, $transaction, $mealVoucherLines);
                    } else {
                        $this->mealVoucherLedger->applyIncome($account, $transaction);
                    }
                }

                $account->current_balance += $amount;
                $account->save();

                $transactions->push($transaction);
            }

            return $transactions;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCurrencyFields(array $header, Account $account, Carbon $date, float $amount): array
    {
        $currencyCode = $account->currency_code;

        $exchangeRate = 1.0;
        if (! empty($header['manual_rate'])) {
            $exchangeRate = (float) $header['manual_rate'];
        } elseif ($currencyCode !== 'EUR') {
            $exchangeRate = $this->currencyConverter->getRateToBase($currencyCode, $date);
        }

        return [
            'currency_code' => $currencyCode,
            'exchange_rate_to_base' => $exchangeRate,
            'amount_base' => round($amount * $exchangeRate, 2),
            'original_amount' => $header['original_amount'] ?? null,
            'original_currency_code' => $header['original_currency_code'] ?? null,
        ];
    }
}
