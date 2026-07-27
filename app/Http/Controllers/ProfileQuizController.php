<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Controller per gestire il quiz di profilazione utente.
 *
 * Il quiz viene mostrato dopo la registrazione e validazione email,
 * prima della creazione della prima household. Permette di configurare
 * le preferenze utente per abilitare/disabilitare moduli specifici.
 */
class ProfileQuizController extends Controller
{
    /**
     * Mostra il quiz di profilazione.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Se l'utente ha già completato il quiz, reindirizza alla dashboard o household
        if ($user->profile_completed) {
            if ($user->active_household_id) {
                return redirect()->route('dashboard');
            }

            // Ha completato il quiz ma non ha household, vai alla creazione/selezione
            $householdsCount = $user->households()->count();
            if ($householdsCount === 0) {
                return redirect()->route('households.create');
            }

            return redirect()->route('households.select');
        }

        return Inertia::render('ProfileQuiz/Show', [
            'currentSettings' => $user->profile_settings ?? [],
        ]);
    }

    /**
     * Salva le risposte del quiz di profilazione.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'family_status' => 'required|string|in:single,couple,family',
            'tracks_investments' => 'required|boolean',
        ]);

        $user = $request->user();

        // Salva le impostazioni del profilo
        $user->update([
            'profile_completed' => true,
            'profile_settings' => [
                'family_status' => $validated['family_status'],
                'tracks_investments' => $validated['tracks_investments'],
                'completed_at' => now()->toISOString(),
            ],
        ]);

        // Reindirizza alla creazione/selezione household
        $householdsCount = $user->households()->count();

        if ($householdsCount === 0) {
            return redirect()->route('households.create')
                ->with('success', 'Configurazione profilo completata! Ora crea la tua prima household.');
        }

        return redirect()->route('households.select')
            ->with('success', 'Configurazione profilo completata!');
    }

    /**
     * Mostra il form di modifica delle impostazioni di profilazione.
     */
    public function edit(Request $request)
    {
        $user = $request->user();

        $currentSettings = $user->profile_settings ?? [];
        if (empty($currentSettings)) {
            $currentSettings = [
                'family_status' => 'single',
                'tracks_investments' => false,
            ];
        }

        return Inertia::render('ProfileQuiz/Edit', [
            'currentSettings' => $currentSettings,
        ]);
    }

    /**
     * Aggiorna le risposte del quiz dal profilo.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'family_status' => 'required|string|in:single,couple,family',
            'tracks_investments' => 'required|boolean',
        ]);

        $user = $request->user();

        // Aggiorna le impostazioni mantenendo la data di primo completamento
        $currentSettings = $user->profile_settings ?? [];

        $user->update([
            'profile_settings' => [
                'family_status' => $validated['family_status'],
                'tracks_investments' => $validated['tracks_investments'],
                'completed_at' => $currentSettings['completed_at'] ?? now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ]);

        return redirect()->route('profile.edit')
            ->with('success', 'Impostazioni di profilazione aggiornate con successo.');
    }
}
