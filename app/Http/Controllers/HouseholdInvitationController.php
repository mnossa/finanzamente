<?php

namespace App\Http\Controllers;

use App\Events\HouseholdMemberAdded;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdInvitationController extends Controller
{
    /**
     * Mostra il form di registrazione con l'invito precompilato.
     */
    public function showRegisterForm(string $token): Response|RedirectResponse
    {
        $invitation = HouseholdInvitation::with('household', 'invitedBy')
            ->byToken($token)
            ->first();

        if (!$invitation) {
            return redirect()->route('register')
                ->with('error', 'Invito non trovato.');
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('login')
                ->with('info', 'Questo invito è già stato accettato. Effettua il login per accedere alla household.');
        }

        if ($invitation->isExpired()) {
            return redirect()->route('register')
                ->with('error', 'Questo invito è scaduto. Chiedi un nuovo invito.');
        }

        // Se l'utente è già loggato, accetta direttamente l'invito
        if (Auth::check()) {
            return $this->acceptInvitation($token);
        }

        // Controlla se l'utente esiste già
        $existingUser = User::where('email', $invitation->email)->first();
        if ($existingUser) {
            return redirect()->route('login')
                ->with('info', 'Esiste già un account con questa email. Effettua il login per accettare l\'invito.');
        }

        return Inertia::render('Auth/RegisterWithInvitation', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'householdName' => $invitation->household->name,
                'inviterName' => $invitation->invitedBy->name,
                'role' => $invitation->role === 'guest' ? 'Ospite' : 'Membro',
                'expiresAt' => $invitation->expires_at->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * Registra un nuovo utente e accetta automaticamente l'invito.
     */
    public function registerAndAccept(Request $request, string $token): RedirectResponse
    {
        $invitation = HouseholdInvitation::with('household', 'invitedBy')
            ->byToken($token)
            ->valid()
            ->first();

        if (!$invitation) {
            return back()->withErrors(['email' => 'Invito non valido o scaduto.']);
        }

        // Verifica che l'email corrisponda
        if (strtolower($request->email) !== strtolower($invitation->email)) {
            return back()->withErrors(['email' => 'L\'email inserita non corrisponde all\'invito.']);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'user_type' => 'required|in:persona,partita_iva',
            'fiscal_code' => [
                'nullable',
                'string',
                'size:16',
                'regex:/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i',
            ],
            'vat_number' => [
                'nullable',
                'string',
                'size:11',
                'regex:/^[0-9]{11}$/',
            ],
        ]);

        // Crea l'utente
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => $request->user_type,
            'fiscal_code' => $request->user_type === 'persona' && $request->filled('fiscal_code') ? strtoupper($request->fiscal_code) : null,
            'vat_number' => $request->user_type === 'partita_iva' && $request->filled('vat_number') ? $request->vat_number : null,
        ]);

        event(new Registered($user));

        // Aggiungi l'utente alla household
        $invitation->household->users()->attach($user->id, [
            'role' => $invitation->role,
            'permissions' => json_encode(['view_only' => $invitation->role === 'guest']),
        ]);

        // Imposta la household come attiva
        $user->update(['active_household_id' => $invitation->household_id]);

        // Segna l'invito come accettato
        $invitation->markAsAccepted();

        // Emetti evento
        event(new HouseholdMemberAdded(
            $invitation->household,
            $user,
            $invitation->invitedBy,
            $invitation->role
        ));

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', "Ti sei registrato e unito alla household '{$invitation->household->name}'!");
    }

    /**
     * Accetta un invito per un utente già registrato.
     */
    public function acceptInvitation(string $token): RedirectResponse
    {
        $invitation = HouseholdInvitation::with('household', 'invitedBy')
            ->byToken($token)
            ->valid()
            ->first();

        if (!$invitation) {
            return redirect()->route('login')
                ->with('error', 'Invito non valido o scaduto.');
        }

        // L'utente deve essere autenticato
        if (!Auth::check()) {
            // Salva il token nella sessione per processarlo dopo il login
            session(['pending_invitation_token' => $token]);
            
            return redirect()->route('login')
                ->with('info', 'Effettua il login per accettare l\'invito.');
        }

        $user = Auth::user();

        // Verifica che l'email corrisponda
        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return redirect()->route('dashboard')
                ->with('error', 'Questo invito è destinato a un altro indirizzo email.');
        }

        // Verifica che l'utente non sia già nella household
        if ($invitation->household->users()->where('user_id', $user->id)->exists()) {
            $invitation->markAsAccepted();
            return redirect()->route('dashboard')
                ->with('info', 'Fai già parte di questa household.');
        }

        // Aggiungi l'utente alla household
        $invitation->household->users()->attach($user->id, [
            'role' => $invitation->role,
            'permissions' => json_encode(['view_only' => $invitation->role === 'guest']),
        ]);

        // Imposta la household come attiva se l'utente non ne ha una
        if (!$user->active_household_id) {
            $user->update(['active_household_id' => $invitation->household_id]);
        }

        // Segna l'invito come accettato
        $invitation->markAsAccepted();

        // Emetti evento
        event(new HouseholdMemberAdded(
            $invitation->household,
            $user,
            $invitation->invitedBy,
            $invitation->role
        ));

        return redirect()->route('dashboard')
            ->with('success', "Ti sei unito alla household '{$invitation->household->name}'!");
    }

    /**
     * Processa inviti pendenti dopo il login (per utenti già registrati).
     */
    public static function processPendingInvitation(User $user): void
    {
        $token = session('pending_invitation_token');
        
        if (!$token) {
            // Controlla anche inviti non accettati per l'email dell'utente
            $pendingInvitations = HouseholdInvitation::with('household', 'invitedBy')
                ->forEmail($user->email)
                ->valid()
                ->get();

            foreach ($pendingInvitations as $invitation) {
                // Verifica che l'utente non sia già nella household
                if (!$invitation->household->users()->where('user_id', $user->id)->exists()) {
                    // Aggiungi l'utente alla household
                    $invitation->household->users()->attach($user->id, [
                        'role' => $invitation->role,
                        'permissions' => json_encode(['view_only' => $invitation->role === 'guest']),
                    ]);

                    // Imposta la household come attiva se l'utente non ne ha una
                    if (!$user->active_household_id) {
                        $user->update(['active_household_id' => $invitation->household_id]);
                    }

                    // Segna l'invito come accettato
                    $invitation->markAsAccepted();

                    // Emetti evento
                    event(new HouseholdMemberAdded(
                        $invitation->household,
                        $user,
                        $invitation->invitedBy,
                        $invitation->role
                    ));
                }
            }
            
            return;
        }

        session()->forget('pending_invitation_token');

        $invitation = HouseholdInvitation::with('household', 'invitedBy')
            ->byToken($token)
            ->valid()
            ->first();

        if (!$invitation || strtolower($user->email) !== strtolower($invitation->email)) {
            return;
        }

        // Verifica che l'utente non sia già nella household
        if ($invitation->household->users()->where('user_id', $user->id)->exists()) {
            $invitation->markAsAccepted();
            return;
        }

        // Aggiungi l'utente alla household
        $invitation->household->users()->attach($user->id, [
            'role' => $invitation->role,
            'permissions' => json_encode(['view_only' => $invitation->role === 'guest']),
        ]);

        // Imposta la household come attiva se l'utente non ne ha una
        if (!$user->active_household_id) {
            $user->update(['active_household_id' => $invitation->household_id]);
        }

        // Segna l'invito come accettato
        $invitation->markAsAccepted();

        // Emetti evento
        event(new HouseholdMemberAdded(
            $invitation->household,
            $user,
            $invitation->invitedBy,
            $invitation->role
        ));
    }
}
