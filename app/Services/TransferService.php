<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransferService
{
    /**
     * Create a transfer and two linked transactions atomically.
     *
     * $data keys expected:
     * - source_account_id, destination_account_id
     * - source_amount, source_currency
     * - dest_currency
     * - exchange_rate (optional) — REQUIRED if source_currency != dest_currency
     * - fee (optional)
     * - initiated_by (optional user id)
     * - source_category_id (required) - category for source transaction (type=expense)
     * - dest_category_id (required) - category for destination transaction (type=income)
     * - date (optional)
     */
    public function createTransfer(array $data): Transfer
    {
        return DB::transaction(function () use ($data) {
            $sourceId = (int) $data['source_account_id'];
            $destId = (int) $data['destination_account_id'];

            // Lock accounts in deterministic order to avoid deadlocks
            $ids = [$sourceId, $destId];
            sort($ids);

            $accounts = Account::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if (! isset($accounts[$sourceId]) || ! isset($accounts[$destId])) {
                throw new \RuntimeException('One or both accounts not found');
            }

            // compute dest_amount if not provided
            $sourceAmount = (float) $data['source_amount'];
            $sourceAmount = abs($sourceAmount);

            $exchangeRate = $data['exchange_rate'] ?? null;
            $destCurrency = $data['dest_currency'] ?? $data['source_currency'];

            // If cross-currency transfer, exchange_rate is required (frontend should provide it).
            if ($destCurrency !== $data['source_currency'] && $exchangeRate === null) {
                throw ValidationException::withMessages([
                    'exchange_rate' => ['Exchange rate is required for cross-currency transfers.'],
                ]);
            }

            // Compute dest amount server-side to be authoritative. Use 8 decimal places for transfer-level precision.
            if ($exchangeRate !== null) {
                $destAmount = round($sourceAmount * (float) $exchangeRate, 8);
            } else {
                // same currency: dest equals source
                $destAmount = round($sourceAmount, 8);
            }

            $transfer = Transfer::create([
                'uuid' => Str::uuid()->toString(),
                'source_account_id' => $sourceId,
                'destination_account_id' => $destId,
                'source_amount' => $sourceAmount,
                'source_currency' => $data['source_currency'],
                'dest_amount' => $destAmount,
                'dest_currency' => $data['dest_currency'] ?? $data['source_currency'],
                'exchange_rate' => $exchangeRate,
                'fee' => $data['fee'] ?? null,
                'user_id' => $data['initiated_by'] ?? $data['user_id'] ?? null,
                'status' => 'completed',
            ]);

            // Create source transaction (expense - negative amount)
            $sourceTx = Transaction::create([
                'user_id' => $data['initiated_by'] ?? null,
                'account_id' => $sourceId,
                'category_id' => $data['source_category_id'],
                'amount' => -abs(round($sourceAmount, 2)),
                'currency_code' => $data['source_currency'],
                'date' => $data['date'] ?? now()->toDateString(),
                'description' => $data['description'] ?? 'Transfer out: '.$transfer->uuid,
                'is_private' => $data['is_private'] ?? false,
                'transfer_id' => $transfer->id,
            ]);

            // Create destination transaction (income - positive amount)
            $destTx = Transaction::create([
                'user_id' => $data['initiated_by'] ?? null,
                'account_id' => $destId,
                'category_id' => $data['dest_category_id'],
                'amount' => abs(round($destAmount, 2)),
                'currency_code' => $data['dest_currency'] ?? $data['source_currency'],
                'date' => $data['date'] ?? now()->toDateString(),
                'description' => $data['description'] ?? 'Transfer in: '.$transfer->uuid,
                'is_private' => $data['is_private'] ?? false,
                'transfer_id' => $transfer->id,
            ]);

            // Optionally, we could attach fee as separate transaction; skip for now.

            // Return transfer with transactions loaded
            return $transfer->load(['sourceAccount', 'destinationAccount', 'transactions']);
        });
    }
}
