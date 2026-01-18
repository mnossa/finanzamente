<?php

namespace App\Policies;

use App\Models\InterHouseholdTransfer;
use App\Models\User;

class InterHouseholdTransferPolicy
{
    /**
     * Determina se l'utente può visualizzare qualsiasi trasferimento inter-household
     */
    public function viewAny(User $user): bool
    {
        // L'utente può vedere i trasferimenti delle sue households
        return true;
    }

    /**
     * Determina se l'utente può visualizzare il trasferimento
     */
    public function view(User $user, InterHouseholdTransfer $transfer): bool
    {
        // L'utente può visualizzare se appartiene alla household sorgente o destinataria
        return $transfer->isSourceHouseholdMember($user) 
            || $transfer->isDestinationHouseholdMember($user);
    }

    /**
     * Determina se l'utente può creare un nuovo trasferimento inter-household
     */
    public function create(User $user): bool
    {
        // L'utente può creare trasferimenti se ha almeno una household
        return $user->households()->exists();
    }

    /**
     * Determina se l'utente può approvare il trasferimento
     */
    public function approve(User $user, InterHouseholdTransfer $transfer): bool
    {
        return $transfer->canBeApprovedBy($user);
    }

    /**
     * Determina se l'utente può rifiutare il trasferimento
     */
    public function reject(User $user, InterHouseholdTransfer $transfer): bool
    {
        return $transfer->canBeRejectedBy($user);
    }

    /**
     * Determina se l'utente può annullare il trasferimento
     */
    public function cancel(User $user, InterHouseholdTransfer $transfer): bool
    {
        return $transfer->canBeCancelledBy($user);
    }

    /**
     * Determina se l'utente può eliminare il trasferimento
     */
    public function delete(User $user, InterHouseholdTransfer $transfer): bool
    {
        // Solo l'utente sorgente può eliminare, e solo se pending o cancelled
        return $transfer->source_user_id === $user->id 
            && in_array($transfer->status, ['pending', 'cancelled', 'rejected']);
    }
}
