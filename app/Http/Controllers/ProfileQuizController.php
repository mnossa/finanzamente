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
        
        // Pre-popola has_vat dal tipo utente dichiarato in registrazione
        $currentSettings = $user->profile_settings ?? [];
        if (empty($currentSettings)) {
            $currentSettings['has_vat'] = $user->user_type === 'partita_iva';
        }
        
        return Inertia::render('ProfileQuiz/Show', [
            'currentSettings' => $currentSettings,
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
        
        // Deduciamo has_vat dal campo user_type (dichiarato in registrazione)
        $hasVat = $user->user_type === 'partita_iva';
        
        // Salva le impostazioni del profilo
        $user->update([
            'profile_completed' => true,
            'profile_settings' => [
                'has_vat' => $hasVat,
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
        
        // Se non ha profile_settings, deduciamo has_vat dal tipo utente
        $currentSettings = $user->profile_settings ?? [];
        if (empty($currentSettings)) {
            $currentSettings = [
                'has_vat' => $user->user_type === 'partita_iva',
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
            'has_vat' => 'required|boolean',
            'family_status' => 'required|string|in:single,couple,family',
            'tracks_investments' => 'required|boolean',
            'revenue_threshold' => 'nullable|numeric|min:1|max:10000000',
            'revenue_tracking_enabled' => 'nullable|boolean',
        ]);
        
        $user = $request->user();
        
        // Aggiorna le impostazioni mantenendo la data di primo completamento e i flag notifiche
        $currentSettings = $user->profile_settings ?? [];
        
        $user->update([
            'profile_settings' => [
                'has_vat' => $validated['has_vat'],
                'family_status' => $validated['family_status'],
                'tracks_investments' => $validated['tracks_investments'],
                'revenue_threshold' => $validated['revenue_threshold'] ?? ($currentSettings['revenue_threshold'] ?? 85000),
                'revenue_tracking_enabled' => $validated['revenue_tracking_enabled'] ?? ($currentSettings['revenue_tracking_enabled'] ?? true),
                'revenue_notified_levels' => $currentSettings['revenue_notified_levels'] ?? [],
                'completed_at' => $currentSettings['completed_at'] ?? now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ],
        ]);
        
        return redirect()->route('profile.edit')
            ->with('success', 'Impostazioni di profilazione aggiornate con successo.');
    }

    /**
     * Abilita o disabilita il monitoraggio del fatturato annuo.
     */
    public function toggleRevenueTracking(Request $request)
    {
        $user = $request->user();
        $settings = $user->profile_settings ?? [];
        $settings['revenue_tracking_enabled'] = !($settings['revenue_tracking_enabled'] ?? true);
        $user->update(['profile_settings' => $settings]);

        return back();
    }
}
