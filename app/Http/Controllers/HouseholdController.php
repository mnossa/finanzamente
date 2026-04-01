<?php

namespace App\Http\Controllers;

use App\Events\HouseholdMemberAdded;
use App\Events\HouseholdMemberRemoved;
use App\Http\Requests\StoreHouseholdRequest;
use App\Mail\HouseholdInvitationMail;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class HouseholdController extends Controller
{
    /**
     * Mostra la pagina di selezione household.
     */
    public function select(Request $request)
    {
        $user = $request->user();
        $households = $user->households()
            ->with('owner:id,name,email')
            ->withCount('users')
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'name' => $h->name,
                'owner' => $h->owner,
                'is_owner' => $h->owner_user_id === $user->id,
                'role' => $h->pivot->role,
                'members_count' => $h->users_count,
            ]);

        return Inertia::render('Households/Select', [
            'households' => $households,
        ]);
    }

    /**
     * Mostra il form di creazione household.
     */
    public function create()
    {
        return Inertia::render('Households/Create');
    }

    /**
     * Crea una nuova household e la imposta come attiva.
     */
    public function store(StoreHouseholdRequest $request)
    {
        $user = $request->user();

        // Limite piano Base: massimo 1 household per utente
        if (!$user->isPro()) {
            $existingCount = $user->households()->count();
            $maxHouseholds = config('plans.base_limits.max_households', 1);

            if ($existingCount >= $maxHouseholds) {
                return redirect()
                    ->route('households.create')
                    ->with('error', 'Il piano Base permette una sola household. Passa al piano Pro per crearne altre.');
            }
        }

        // Crea la household
        $household = Household::create([
            'name' => $request->name,
            'owner_user_id' => $user->id,
            'financial_management_type' => $request->financial_management_type,
        ]);

        // Se è debt_balancing e balance_type è custom, avremo percentuali in futuro
        // Per ora salviamo solo il tipo, le percentuali saranno configurate dopo

        // Aggiungi l'utente come owner nella tabella pivot
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'supervise' => true]),
        ]);

        // Imposta come household attiva
        $user->update(['active_household_id' => $household->id]);

        return redirect()->route('dashboard')
            ->with('success', 'Household creata con successo!');
    }

    /**
     * Cambia la household attiva dell'utente.
     */
    public function setActive(Request $request, Household $household)
    {
        $user = $request->user();

        // Verifica che l'utente faccia parte della household
        if (!$user->households()->where('households.id', $household->id)->exists()) {
            abort(403, 'Non hai accesso a questa household.');
        }

        $user->update(['active_household_id' => $household->id]);

        return redirect()->route('dashboard')
            ->with('success', "Household '{$household->name}' selezionata.");
    }

    /**
     * Mostra i dettagli/impostazioni della household.
     */
    public function show(Request $request, Household $household)
    {
        $user = $request->user();

        // Verifica accesso
        if (!$user->households()->where('households.id', $household->id)->exists()) {
            abort(403, 'Non hai accesso a questa household.');
        }

        $household->load(['owner:id,name,email', 'users:id,name,email']);

        $members = $household->users->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pivot->role,
            'is_owner' => $u->id === $household->owner_user_id,
        ]);

        // Recupera inviti pendenti
        $pendingInvitations = HouseholdInvitation::where('household_id', $household->id)
            ->valid()
            ->with('invitedBy:id,name')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'role' => $inv->role,
                'invited_by' => $inv->invitedBy->name,
                'expires_at' => $inv->expires_at->format('d/m/Y H:i'),
                'created_at' => $inv->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Households/Show', [
            'household' => [
                'id' => $household->id,
                'name' => $household->name,
                'owner' => $household->owner,
                'is_owner' => $household->owner_user_id === $user->id,
                'financial_management_type' => $household->financial_management_type,
                'financial_management_type_label' => $household->getFinancialManagementTypeLabel(),
                'balance_percentages' => $household->balance_percentages ?: $household->calculateEqualPercentages(),
                'enable_turn_suggestions' => $household->enable_turn_suggestions,
                'turn_suggestion_settings' => $household->turn_suggestion_settings,
                'exclude_inter_transfers_from_stats' => $household->exclude_inter_transfers_from_stats,
            ],
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    /**
     * Aggiorna le impostazioni della household.
     */
    public function update(Request $request, Household $household)
    {
        $user = $request->user();

        // Solo l'owner può modificare
        if ($household->owner_user_id !== $user->id) {
            abort(403, 'Solo il proprietario può modificare la household.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'financial_management_type' => 'sometimes|string|in:debt_balancing,shared_wallet',
            'balance_percentages' => 'sometimes|array',
            'balance_percentages.*' => 'numeric|min:0|max:100',
            'enable_turn_suggestions' => 'sometimes|boolean',
            'turn_suggestion_settings' => 'sometimes|array',
            'exclude_inter_transfers_from_stats' => 'sometimes|boolean',
        ]);

        // dd($data);

        // Valida le percentuali se è debt_balancing
        if (isset($data['financial_management_type']) && $data['financial_management_type'] === 'debt_balancing' && isset($data['balance_percentages'])) {
            $total = array_sum($data['balance_percentages']);
            if (abs($total - 100) > 0.01) {
                return back()->withErrors(['balance_percentages' => 'Le percentuali devono sommare esattamente al 100%.']);
            }
        }

        // Se il suggeritore di turni è abilitato, deve essere una household con bilanciamento debiti
        if (isset($data['enable_turn_suggestions']) && $data['enable_turn_suggestions']) {
            $managementType = $data['financial_management_type'] ?? $household->financial_management_type;
            if ($managementType !== 'debt_balancing') {
                return back()->withErrors(['enable_turn_suggestions' => 'Il suggeritore di turni è disponibile solo per household con bilanciamento debiti.']);
            }
        }

        $household->update($data);

        return back()->with('success', 'Household aggiornata con successo.');
    }

    /**
     * Elimina la household.
     */
    public function destroy(Request $request, Household $household)
    {
        $user = $request->user();

        // Solo l'owner può eliminare
        if ($household->owner_user_id !== $user->id) {
            abort(403, 'Solo il proprietario può eliminare la household.');
        }

        // Se è la household attiva, resetta
        if ($user->active_household_id === $household->id) {
            $user->update(['active_household_id' => null]);
        }

        $household->delete();

        // Reindirizza alla selezione se l'utente ha altre household
        $remainingCount = $user->households()->count();
        
        if ($remainingCount > 0) {
            return redirect()->route('households.select')
                ->with('success', 'Household eliminata.');
        }

        return redirect()->route('households.create')
            ->with('info', 'Household eliminata. Crea una nuova household per continuare.');
    }

    /**
     * Invita un utente nella household.
     * Se l'utente esiste, viene aggiunto direttamente.
     * Se non esiste, viene creato un invito con link di registrazione.
     */
    public function invite(Request $request, Household $household)
    {
        $user = $request->user();

        // Verifica permessi (owner o chi ha permesso manage)
        $membership = $user->households()
            ->where('households.id', $household->id)
            ->first();

        if (!$membership) {
            abort(403, 'Non hai accesso a questa household.');
        }

        $permissions = json_decode($membership->pivot->permissions ?? '{}', true);
        $canManage = $household->owner_user_id === $user->id 
            || ($permissions['manage'] ?? false);

        if (!$canManage) {
            abort(403, 'Non hai i permessi per invitare membri.');
        }

        $data = $request->validate([
            'email' => 'required|email',
            'role' => 'sometimes|string|in:member,guest',
        ]);

        $email = strtolower($data['email']);
        $role = $data['role'] ?? 'member';

        // Controlla se esiste già un membro con questa email
        $existingMember = $household->users()->where('users.email', $email)->first();
        if ($existingMember) {
            return back()->withErrors(['email' => 'Questo utente fa già parte della household.']);
        }

        $invitedUser = User::where('email', $email)->first();
        
        if ($invitedUser) {
            // L'utente esiste: aggiungilo direttamente alla household
            $household->users()->attach($invitedUser->id, [
                'role' => $role,
                'permissions' => json_encode(['view_only' => $role === 'guest']),
            ]);

            event(new HouseholdMemberAdded($household, $invitedUser, $user, $role));

            return back()->with('success', "Utente {$invitedUser->name} aggiunto alla household.");
        }

        // L'utente non esiste: crea un invito e invia email
        // Invalida eventuali inviti precedenti per la stessa email/household
        HouseholdInvitation::where('household_id', $household->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by_user_id' => $user->id,
            'email' => $email,
            'role' => $role,
            'token' => HouseholdInvitation::generateToken(),
            'expires_at' => now()->addDays(7), // Scade dopo 7 giorni
        ]);

        // Invia email di invito
        Mail::to($email)->send(new HouseholdInvitationMail($invitation, true));

        return back()->with('success', "Invito inviato a {$email}. L'utente riceverà un'email con le istruzioni per registrarsi e unirsi alla household.");
    }

    /**
     * Rimuove un membro dalla household.
     */
    public function removeMember(Request $request, Household $household, User $member)
    {
        $user = $request->user();

        // Non si può rimuovere l'owner
        if ($member->id === $household->owner_user_id) {
            abort(403, 'Non puoi rimuovere il proprietario della household.');
        }

        // Verifica permessi
        $canManage = $household->owner_user_id === $user->id;

        if (!$canManage) {
            abort(403, 'Solo il proprietario può rimuovere membri.');
        }

        $household->users()->detach($member->id);

        // Se il membro aveva questa household come attiva, resettala
        if ($member->active_household_id === $household->id) {
            $member->update(['active_household_id' => null]);
        }

        event(new HouseholdMemberRemoved($household, $member, $user));

        return back()->with('success', "Membro {$member->name} rimosso dalla household.");
    }

    /**
     * Lascia una household (per membri non-owner).
     */
    public function leave(Request $request, Household $household)
    {
        $user = $request->user();

        // L'owner non può lasciare, deve eliminare o trasferire ownership
        if ($household->owner_user_id === $user->id) {
            return back()->withErrors(['error' => 'Il proprietario non può abbandonare la household. Trasferisci la proprietà o eliminala.']);
        }

        $household->users()->detach($user->id);

        // Resetta la household attiva se era questa
        if ($user->active_household_id === $household->id) {
            $user->update(['active_household_id' => null]);
        }

        $remainingCount = $user->households()->count();

        if ($remainingCount > 0) {
            return redirect()->route('households.select')
                ->with('success', 'Hai lasciato la household.');
        }

        return redirect()->route('households.create')
            ->with('info', 'Hai lasciato la household. Crea o unisciti a una household per continuare.');
    }

    /**
     * Cancella un invito pendente.
     */
    public function cancelInvitation(Request $request, Household $household, HouseholdInvitation $invitation)
    {
        $user = $request->user();

        // Verifica che l'invito appartenga alla household
        if ($invitation->household_id !== $household->id) {
            abort(404, 'Invito non trovato.');
        }

        // Verifica permessi (owner o chi ha permesso manage)
        $membership = $user->households()
            ->where('households.id', $household->id)
            ->first();

        if (!$membership) {
            abort(403, 'Non hai accesso a questa household.');
        }

        $permissions = json_decode($membership->pivot->permissions ?? '{}', true);
        $canManage = $household->owner_user_id === $user->id 
            || ($permissions['manage'] ?? false);

        if (!$canManage) {
            abort(403, 'Non hai i permessi per gestire gli inviti.');
        }

        $email = $invitation->email;
        $invitation->delete();

        return back()->with('success', "Invito a {$email} cancellato.");
    }

    /**
     * Reinvia un invito pendente.
     */
    public function resendInvitation(Request $request, Household $household, HouseholdInvitation $invitation)
    {
        $user = $request->user();

        // Verifica che l'invito appartenga alla household
        if ($invitation->household_id !== $household->id) {
            abort(404, 'Invito non trovato.');
        }

        // Verifica permessi
        $membership = $user->households()
            ->where('households.id', $household->id)
            ->first();

        if (!$membership) {
            abort(403, 'Non hai accesso a questa household.');
        }

        $permissions = json_decode($membership->pivot->permissions ?? '{}', true);
        $canManage = $household->owner_user_id === $user->id 
            || ($permissions['manage'] ?? false);

        if (!$canManage) {
            abort(403, 'Non hai i permessi per gestire gli inviti.');
        }

        // Aggiorna la data di scadenza
        $invitation->update([
            'expires_at' => now()->addDays(7),
            'token' => HouseholdInvitation::generateToken(),
        ]);

        // Reinvia email
        Mail::to($invitation->email)->send(new HouseholdInvitationMail($invitation, true));

        return back()->with('success', "Invito reinviato a {$invitation->email}.");
    }
}
