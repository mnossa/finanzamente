# Quiz di Profilazione Utente

## Descrizione
Il quiz di profilazione è una funzionalità che viene mostrata ai nuovi utenti dopo la registrazione e la validazione dell'email, prima della creazione del primo household.

L'obiettivo è personalizzare l'interfaccia e abilitare solo i moduli necessari in base alle esigenze dell'utente.

## Flusso Utente

### Nuovo Utente
1. L'utente si registra e valida l'email
2. Al primo login, viene reindirizzato automaticamente al quiz di profilazione (`/profile-quiz`)
3. Risponde alle 2 domande:
   - **Qual è la tua situazione familiare?** (Single, Coppia, Famiglia)
   - **Gestisci investimenti?** (Sì/No)
4. Il sistema deduce automaticamente se ha una Partita IVA dal campo `user_type` dichiarato in registrazione
5. Dopo aver completato il quiz, viene reindirizzato alla creazione/selezione household
6. Le preferenze vengono salvate in `users.profile_settings` (JSON)

### Utente Esistente
Gli utenti che già hanno una household vengono fatti passare senza richiedere il quiz (retrocompatibilità).
Possono comunque modificare le impostazioni dal profilo utente.

### Modifica Impostazioni
Gli utenti possono modificare le impostazioni del quiz in qualsiasi momento:
1. Vai a **Profilo** → **Impostazioni di Profilazione**
2. Clicca su **Modifica Impostazioni**
3. Aggiorna le risposte (inclusa la Partita IVA se necessario) e salva

**Nota**: Il campo Partita IVA viene dedotto automaticamente dal `user_type` dichiarato in registrazione, ma può essere modificato successivamente dal profilo.

## Struttura Database

### Migration
File: `database/migrations/2026_02_17_171226_add_profile_completed_to_users_table.php`

Campi aggiunti alla tabella `users`:
- `profile_completed` (boolean, default: false) - Indica se l'utente ha completato il quiz
- `profile_settings` (json, nullable) - Salva le risposte del quiz

Esempio di `profile_settings`:
```json
{
  "has_vat": false,
  "family_status": "single",
  "tracks_investments": false,
  "completed_at": "2026-02-17T17:30:00.000000Z",
  "updated_at": "2026-02-17T18:45:00.000000Z"
}
```

## Backend

### Controller
- **ProfileQuizController** (`app/Http/Controllers/ProfileQuizController.php`)
  - `show()` - Mostra il quiz di profilazione (solo 2 domande)
  - `store()` - Salva le risposte e deduce automaticamente `has_vat` da `user_type`
  - `edit()` - Mostra il form di modifica delle impostazioni (tutte e 3 le impostazioni)
  - `update()` - Aggiorna le impostazioni

### Middleware
- **EnsureProfileCompleted** (`app/Http/Middleware/EnsureProfileCompleted.php`)
  - Verifica se l'utente ha completato il quiz
  - Reindirizza al quiz se non completato
  - **Eccezione**: gli utenti con almeno una household vengono fatti passare (retrocompatibilità)

### Rotte
```php
// Quiz di profilazione (auth + verified)
Route::get('/profile-quiz', [ProfileQuizController::class, 'show'])->name('profile-quiz.show');
Route::post('/profile-quiz', [ProfileQuizController::class, 'store'])->name('profile-quiz.store');

// Modifica impostazioni dal profilo (auth + verified + household)
Route::get('/profile/quiz-settings', [ProfileQuizController::class, 'edit'])->name('profile.quiz-settings.edit');
Route::patch('/profile/quiz-settings', [ProfileQuizController::class, 'update'])->name('profile.quiz-settings.update');
```

## Frontend

### Pagine
- **ProfileQuiz/Show.tsx** - Quiz di profilazione iniziale (2 domande: situazione familiare e investimenti)
- **ProfileQuiz/Edit.tsx** - Modifica impostazioni (tutte e 3: P.IVA, situazione familiare, investimenti)
- **Profile/Partials/ProfileQuizSettingsCard.tsx** - Card nella pagina profilo

### Componenti
Tutte le pagine utilizzano:
- `AuthenticatedSimpleLayout` (per il quiz iniziale)
- `AuthenticatedLayout` (per la modifica dal profilo)
- Componenti comuni: `InputLabel`, `InputError`, `PrimaryButton`, `SecondaryButton`

## Validazione

Le risposte del quiz vengono validate nel controller:

**Quiz iniziale (store)**:
```php
$validated = $request->validate([
    'family_status' => 'required|string|in:single,couple,family',
    'tracks_investments' => 'required|boolean',
]);
// has_vat viene dedotto da user_type
$hasVat = $user->user_type === 'partita_iva';
```

**Modifica dal profilo (update)**:
```php
$validated = $request->validate([
    'has_vat' => 'required|boolean',
    'family_status' => 'required|string|in:single,couple,family',
    'tracks_investments' => 'required|boolean',
]);
```

## Utilizzo Futuro

I dati del quiz possono essere utilizzati per:
1. **Personalizzare la dashboard** - Nascondere/mostrare widget basati sulle preferenze
2. **Abilitare/disabilitare moduli** - Es: modulo IVA solo se `has_vat: true`
3. **Suggerire funzionalità** - Es: suggerire investimenti se `tracks_investments: false`
4. **Analytics** - Tracciare i pattern di utilizzo basati sul profilo utente

## Note

- Il quiz è opzionale per utenti esistenti (con household)
- Le impostazioni possono essere modificate in qualsiasi momento
- Le risposte vengono salvate in formato JSON per flessibilità futura
- Il campo `completed_at` traccia quando l'utente ha completato il quiz per la prima volta
- Il campo `updated_at` traccia l'ultima modifica alle impostazioni

## Testing

Per testare il flusso:
1. Crea un nuovo utente e valida l'email
2. Al primo login, dovresti essere reindirizzato a `/profile-quiz`
3. Completa il quiz
4. Verifica che vieni reindirizzato alla creazione household
5. Vai al profilo e controlla che le impostazioni siano visualizzate correttamente
6. Modifica le impostazioni e verifica che vengano salvate

```bash
# Esegui la migration
make migrate

# Seed del database
make seed

# Test
make test
```
