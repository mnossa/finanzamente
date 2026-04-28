<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Refund;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefundService
{
    /**
     * Crea un rimborso e la transazione di entrata collegata atomicamente.
     *
     * @param  array  $data  Dati del rimborso:
     *                       - original_transaction_id: ID della transazione di spesa originale
     *                       - amount: Importo del rimborso (valore assoluto, max = importo spesa originale - già rimborsato)
     *                       - user_id: ID dell'utente che crea il rimborso
     *                       - category_id: ID della categoria per la transazione di rimborso (tipo income)
     *                       - date: Data del rimborso (opzionale, default oggi)
     *                       - description: Descrizione del rimborso (opzionale)
     *                       - is_private: Se la transazione di rimborso è privata (opzionale, default false)
     *
     * @throws ValidationException
     */
    public function createRefund(array $data): Refund
    {
        return DB::transaction(function () use ($data) {
            $originalTransactionId = (int) $data['original_transaction_id'];

            // Blocca la transazione originale per evitare race condition
            $originalTransaction = Transaction::lockForUpdate()->find($originalTransactionId);

            if (! $originalTransaction) {
                throw ValidationException::withMessages([
                    'original_transaction_id' => ['La transazione originale non esiste.'],
                ]);
            }

            // Verifica che sia una spesa (amount negativo)
            if ((float) $originalTransaction->amount >= 0) {
                throw ValidationException::withMessages([
                    'original_transaction_id' => ['È possibile rimborsare solo transazioni di spesa (importo negativo).'],
                ]);
            }

            // Verifica che non sia già parte di un trasferimento
            if ($originalTransaction->transfer_id) {
                throw ValidationException::withMessages([
                    'original_transaction_id' => ['Non è possibile rimborsare transazioni di trasferimento.'],
                ]);
            }

            // Verifica che non sia già una transazione di rimborso
            if ($originalTransaction->refund_id) {
                throw ValidationException::withMessages([
                    'original_transaction_id' => ['Non è possibile rimborsare una transazione che è già un rimborso.'],
                ]);
            }

            $originalAmount = abs((float) $originalTransaction->amount);
            $alreadyRefunded = $originalTransaction->getTotalRefundedAmount();
            $maxRefundable = $originalAmount - $alreadyRefunded;

            $refundAmount = abs((float) $data['amount']);

            // Verifica che l'importo del rimborso non superi l'importo rimborsabile
            if ($refundAmount > $maxRefundable + 0.001) { // Tolleranza per errori floating point
                throw ValidationException::withMessages([
                    'amount' => [
                        sprintf(
                            'L\'importo del rimborso (%.2f) supera l\'importo massimo rimborsabile (%.2f).',
                            $refundAmount,
                            $maxRefundable
                        ),
                    ],
                ]);
            }

            // Limita l'importo al massimo rimborsabile
            $refundAmount = min($refundAmount, $maxRefundable);

            // Blocca l'account per aggiornare il saldo
            $account = Account::lockForUpdate()->find($originalTransaction->account_id);

            if (! $account) {
                throw ValidationException::withMessages([
                    'original_transaction_id' => ['Il conto associato alla transazione non esiste.'],
                ]);
            }

            // Crea il record del rimborso
            $refund = Refund::create([
                'uuid' => Str::uuid()->toString(),
                'original_transaction_id' => $originalTransactionId,
                'user_id' => $data['user_id'],
                'amount' => round($refundAmount, 2),
                'currency_code' => $originalTransaction->currency_code,
                'status' => 'completed',
                'description' => $data['description'] ?? null,
            ]);

            // Crea la transazione di rimborso (entrata - importo positivo)
            $refundTransaction = Transaction::create([
                'user_id' => $data['user_id'],
                'account_id' => $originalTransaction->account_id,
                'category_id' => $data['category_id'],
                'amount' => round($refundAmount, 2), // Positivo perché è un'entrata
                'currency_code' => $originalTransaction->currency_code,
                'date' => $data['date'] ?? now()->toDateString(),
                'description' => $data['description'] ?? sprintf('Rimborso per: %s', $originalTransaction->description ?? 'Transazione #'.$originalTransactionId),
                'is_private' => $data['is_private'] ?? $originalTransaction->is_private,
                'refund_id' => $refund->id,
            ]);

            // Aggiorna il saldo del conto
            $account->current_balance += $refundAmount;
            $account->save();

            // Carica le relazioni per il ritorno
            return $refund->load(['originalTransaction.account', 'originalTransaction.category', 'refundTransaction', 'user']);
        });
    }

    /**
     * Aggiorna un rimborso esistente.
     */
    public function updateRefund(Refund $refund, array $data): Refund
    {
        return DB::transaction(function () use ($refund, $data) {
            $refundTransaction = $refund->refundTransaction;

            if (! $refundTransaction) {
                throw ValidationException::withMessages([
                    'refund' => ['La transazione di rimborso non esiste.'],
                ]);
            }

            $oldAmount = (float) $refund->amount;
            $newAmount = isset($data['amount']) ? abs((float) $data['amount']) : $oldAmount;
            $amountDiff = $newAmount - $oldAmount;

            // Se l'importo è cambiato, verifica che non superi il massimo rimborsabile
            if ($amountDiff > 0) {
                $originalTransaction = $refund->originalTransaction;
                $originalAmount = abs((float) $originalTransaction->amount);
                $alreadyRefunded = $originalTransaction->getTotalRefundedAmount() - $oldAmount; // Escludi questo rimborso
                $maxRefundable = $originalAmount - $alreadyRefunded;

                if ($newAmount > $maxRefundable + 0.001) {
                    throw ValidationException::withMessages([
                        'amount' => [
                            sprintf(
                                'L\'importo del rimborso (%.2f) supera l\'importo massimo rimborsabile (%.2f).',
                                $newAmount,
                                $maxRefundable
                            ),
                        ],
                    ]);
                }

                $newAmount = min($newAmount, $maxRefundable);
            }

            // Aggiorna il rimborso
            $refund->update([
                'amount' => round($newAmount, 2),
                'description' => $data['description'] ?? $refund->description,
            ]);

            // Aggiorna la transazione di rimborso
            $refundTransaction->update([
                'amount' => round($newAmount, 2),
                'date' => $data['date'] ?? $refundTransaction->date,
                'description' => $data['description'] ?? $refundTransaction->description,
                'is_private' => $data['is_private'] ?? $refundTransaction->is_private,
            ]);

            // Se l'importo è cambiato, aggiorna il saldo del conto
            if (abs($amountDiff) > 0.001) {
                $account = $refundTransaction->account;
                $account->current_balance += $amountDiff;
                $account->save();
            }

            return $refund->fresh(['originalTransaction.account', 'originalTransaction.category', 'refundTransaction', 'user']);
        });
    }

    /**
     * Elimina un rimborso e ripristina il saldo del conto.
     */
    public function deleteRefund(Refund $refund): bool
    {
        return DB::transaction(function () use ($refund) {
            $refundTransaction = $refund->refundTransaction;

            if ($refundTransaction) {
                // Ripristina il saldo del conto
                $account = $refundTransaction->account;
                if ($account) {
                    $account->current_balance -= (float) $refund->amount;
                    $account->save();
                }
            }

            // Il modello Refund si occupa di eliminare la transazione collegata nel booted()
            return $refund->delete();
        });
    }

    /**
     * Ottiene i rimborsi per una transazione originale.
     *
     * @return Collection
     */
    public function getRefundsForTransaction(int $originalTransactionId)
    {
        return Refund::where('original_transaction_id', $originalTransactionId)
            ->with(['refundTransaction', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
