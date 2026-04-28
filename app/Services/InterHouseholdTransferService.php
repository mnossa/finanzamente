<?php

namespace App\Services;

use App\Models\Account;
use App\Models\InterHouseholdTransfer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * InterHouseholdTransferService
 *
 * Gestisce la logica di business per i trasferimenti tra households diverse.
 */
class InterHouseholdTransferService
{
    /**
     * Crea un nuovo trasferimento inter-household
     * Dato che l'utente appartiene a entrambe le households, il trasferimento viene approvato automaticamente
     */
    public function createTransfer(array $data, User $initiator): InterHouseholdTransfer
    {
        // Validazioni di business
        $sourceAccount = Account::findOrFail($data['source_account_id']);
        $destAccount = Account::findOrFail($data['dest_account_id']);

        // Verifica che gli account appartengano a households diverse
        if ($sourceAccount->household_id === $destAccount->household_id) {
            throw new InvalidArgumentException('Gli account devono appartenere a households diverse. Usa i trasferimenti normali per account della stessa household.');
        }

        // Verifica che l'utente appartenga a entrambe le households
        if (! $sourceAccount->household->users()->where('users.id', $initiator->id)->exists()) {
            throw new InvalidArgumentException('Non hai accesso all\'account sorgente.');
        }

        if (! $destAccount->household->users()->where('users.id', $initiator->id)->exists()) {
            throw new InvalidArgumentException('Non hai accesso all\'account destinatario.');
        }

        return DB::transaction(function () use ($data, $initiator, $sourceAccount, $destAccount) {
            $excludeFromStats = (bool) ($data['exclude_from_stats'] ?? false);
            $transferDate = $data['transfer_date'] ?? now()->toDateString();
            $sourceCurrency = $data['source_currency'] ?? $sourceAccount->currency_code;
            $destCurrency = $data['dest_currency'] ?? $destAccount->currency_code;

            // Crea le transazioni con placeholder per la FK (verrà aggiornata dopo)
            $sourceTransaction = Transaction::create([
                'user_id' => $initiator->id,
                'account_id' => $data['source_account_id'],
                'category_id' => null,
                'amount' => -abs($data['source_amount']),
                'currency_code' => $sourceCurrency,
                'date' => $transferDate,
                'description' => $data['description'] ?? "Trasferimento verso {$destAccount->household->name}",
                'recurring' => false,
                'is_private' => false,
            ]);

            $destTransaction = Transaction::create([
                'user_id' => $data['dest_user_id'] ?? $initiator->id,
                'account_id' => $data['dest_account_id'],
                'category_id' => null,
                'amount' => abs($data['dest_amount'] ?? $data['source_amount']),
                'currency_code' => $destCurrency,
                'date' => $transferDate,
                'description' => $data['description'] ?? "Trasferimento da {$sourceAccount->household->name}",
                'recurring' => false,
                'is_private' => false,
            ]);

            // Crea il trasferimento già approvato con i riferimenti alle transazioni
            $transfer = InterHouseholdTransfer::create([
                'source_household_id' => $sourceAccount->household_id,
                'source_account_id' => $data['source_account_id'],
                'source_user_id' => $initiator->id,
                'dest_household_id' => $destAccount->household_id,
                'dest_account_id' => $data['dest_account_id'],
                'dest_user_id' => $data['dest_user_id'] ?? $initiator->id,
                'source_amount' => $data['source_amount'],
                'source_currency' => $sourceCurrency,
                'dest_amount' => $data['dest_amount'] ?? $data['source_amount'],
                'dest_currency' => $destCurrency,
                'exchange_rate' => $data['exchange_rate'] ?? null,
                'fee' => $data['fee'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transfer_date' => $transferDate,
                'exclude_from_stats' => $excludeFromStats,
                'status' => 'approved',
                'source_transaction_id' => $sourceTransaction->id,
                'dest_transaction_id' => $destTransaction->id,
                'approved_at' => now(),
                'approved_by' => $initiator->id,
            ]);

            // Collega le transazioni al trasferimento tramite la FK bidirezionale
            $sourceTransaction->update(['inter_household_transfer_id' => $transfer->id]);
            $destTransaction->update(['inter_household_transfer_id' => $transfer->id]);

            // Aggiorna i saldi degli account
            $sourceAccount->recalculateBalance();
            $destAccount->recalculateBalance();

            return $transfer->fresh();
        });
    }

    /**
     * Approva un trasferimento e crea le transazioni
     */
    public function approveTransfer(InterHouseholdTransfer $transfer, User $approver): InterHouseholdTransfer
    {
        if (! $transfer->isPending()) {
            throw new InvalidArgumentException('Solo i trasferimenti in stato pending possono essere approvati.');
        }

        if (! $transfer->canBeApprovedBy($approver)) {
            throw new InvalidArgumentException('Non hai il permesso di approvare questo trasferimento.');
        }

        return DB::transaction(function () use ($transfer, $approver) {
            // Crea la transazione di uscita per la household sorgente
            $sourceTransaction = Transaction::create([
                'user_id' => $transfer->source_user_id,
                'account_id' => $transfer->source_account_id,
                'category_id' => null, // Nessuna categoria per trasferimenti inter-household
                'amount' => -abs($transfer->source_amount), // Negativo perché è un'uscita
                'currency_code' => $transfer->source_currency,
                'date' => $transfer->transfer_date,
                'description' => $transfer->description
                    ?? "Trasferimento verso {$transfer->destinationHousehold->name}",
                'recurring' => false,
                'is_private' => false,
            ]);

            // Crea la transazione di entrata per la household destinataria
            $destTransaction = Transaction::create([
                'user_id' => $transfer->dest_user_id ?? $approver->id,
                'account_id' => $transfer->dest_account_id,
                'category_id' => null,
                'amount' => abs($transfer->dest_amount), // Positivo perché è un'entrata
                'currency_code' => $transfer->dest_currency,
                'date' => $transfer->transfer_date,
                'description' => $transfer->description
                    ?? "Trasferimento da {$transfer->sourceHousehold->name}",
                'recurring' => false,
                'is_private' => false,
            ]);

            // Aggiorna il trasferimento
            $transfer->update([
                'status' => 'approved',
                'source_transaction_id' => $sourceTransaction->id,
                'dest_transaction_id' => $destTransaction->id,
                'approved_at' => now(),
                'approved_by' => $approver->id,
            ]);

            // Aggiorna i saldi degli account
            $transfer->sourceAccount->recalculateBalance();
            $transfer->destinationAccount->recalculateBalance();

            return $transfer->fresh();
        });
    }

    /**
     * Rifiuta un trasferimento
     */
    public function rejectTransfer(InterHouseholdTransfer $transfer, User $rejector, ?string $reason = null): InterHouseholdTransfer
    {
        if (! $transfer->isPending()) {
            throw new InvalidArgumentException('Solo i trasferimenti in stato pending possono essere rifiutati.');
        }

        if (! $transfer->canBeRejectedBy($rejector)) {
            throw new InvalidArgumentException('Non hai il permesso di rifiutare questo trasferimento.');
        }

        $transfer->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $rejector->id,
            'rejection_reason' => $reason,
        ]);

        return $transfer->fresh();
    }

    /**
     * Annulla un trasferimento (solo se pending)
     */
    public function cancelTransfer(InterHouseholdTransfer $transfer, User $canceller): InterHouseholdTransfer
    {
        if (! $transfer->isPending()) {
            throw new InvalidArgumentException('Solo i trasferimenti in stato pending possono essere annullati.');
        }

        if (! $transfer->canBeCancelledBy($canceller)) {
            throw new InvalidArgumentException('Non hai il permesso di annullare questo trasferimento.');
        }

        $transfer->update([
            'status' => 'cancelled',
        ]);

        return $transfer->fresh();
    }

    /**
     * Elimina un trasferimento e le sue transazioni (se presenti)
     */
    public function deleteTransfer(InterHouseholdTransfer $transfer): bool
    {
        return DB::transaction(function () use ($transfer) {
            // Le transazioni verranno eliminate automaticamente dal booted event del modello
            return $transfer->delete();
        });
    }
}
