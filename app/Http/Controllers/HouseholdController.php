<?php

namespace App\Http\Controllers;

use App\Events\HouseholdMemberAdded;
use App\Events\HouseholdMemberRemoved;
use App\Http\Requests\StoreHouseholdRequest;
use App\Models\Household;
use App\Models\User;
use Illuminate\Http\Request;
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

        // Crea la household
        $household = Household::create([
            'name' => $request->name,
            'owner_user_id' => $user->id,
        ]);

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

        return Inertia::render('Households/Show', [
            'household' => [
                'id' => $household->id,
                'name' => $household->name,
                'owner' => $household->owner,
                'is_owner' => $household->owner_user_id === $user->id,
            ],
            'members' => $members,
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
        ]);

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

        $invitedUser = User::where('email', $data['email'])->first();
        
        if (!$invitedUser) {
            return back()->withErrors(['email' => 'Nessun utente trovato con questa email.']);
        }

        if ($household->users()->where('user_id', $invitedUser->id)->exists()) {
            return back()->withErrors(['email' => 'Questo utente fa già parte della household.']);
        }

        $role = $data['role'] ?? 'member';

        $household->users()->attach($invitedUser->id, [
            'role' => $role,
            'permissions' => json_encode(['view_only' => true]),
        ]);

        event(new HouseholdMemberAdded($household, $invitedUser, $user, $role));

        return back()->with('success', "Utente {$invitedUser->name} aggiunto alla household.");
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
}
