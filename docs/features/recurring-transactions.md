# Sistema di Transazioni Ricorrenti

## Panoramica

Il sistema di transazioni ricorrenti permette di automatizzare la creazione di transazioni ripetute nel tempo (es. affitti, abbonamenti, stipendi).

## Funzionalità Implementate

### 1. Generazione Automatica al Momento della Creazione

Quando si crea una nuova transazione ricorrente:
- Vengono **automaticamente generate tutte le transazioni** dalla data di inizio (`start_date`) fino alla data odierna
- Se la `start_date` è antecedente a oggi, il sistema crea tutte le occorrenze mancanti
- Il saldo degli account viene aggiornato per ogni transazione generata
- L'utente riceve un messaggio di conferma con il numero di transazioni generate

**Esempio:**
```
Data odierna: 18/01/2026
Ricorrenza creata con:
- start_date: 01/10/2025 (3+ mesi fa)
- frequency: monthly
- amount: -500€ (spesa affitto)

Risultato: vengono generate 4 transazioni:
- 01/10/2025
- 01/11/2025
- 01/12/2025
- 01/01/2026
```

### 2. Tracking dell'Ultima Generazione

Il sistema utilizza il campo `last_generated_date` per:
- Tracciare l'ultima data per cui è stata generata una transazione
- Evitare duplicazioni quando vengono generate nuove transazioni
- Calcolare correttamente la prossima data di scadenza

### 3. Generazione Automatica Giornaliera

È stato implementato un comando artisan schedulato che:
- Viene eseguito automaticamente ogni giorno alle 00:01
- Processa tutte le transazioni ricorrenti attive
- Genera le transazioni del giorno corrente se dovute
- Registra log dettagliati delle operazioni

**Comando manuale:**
```bash
php artisan recurring:generate
```

**Comando con data specifica:**
```bash
php artisan recurring:generate --date=2026-01-31
```

### 4. Cambio importo (fork automatico)

Quando modifichi **solo l'importo** (o l'importo insieme ad altri campi) dalla pagina di modifica:

- Le transazioni già generate **mantengono l'importo storico**.
- La ricorrenza attuale riceve una `end_date` e viene collegata alla nuova ricorrenza (`successor` / `predecessor`).
- La nuova ricorrenza parte dalla **data di decorrenza** (default: prossima occorrenza non ancora generata; modificabile in UI).
- Aggiornamenti a categoria, descrizione o conto **senza** cambio importo continuano a propagarsi su tutte le transazioni collegate.

### 5. Suffisso periodo in descrizione

Ogni transazione generata automaticamente include in descrizione un suffisso in italiano (es. ` - Marzo 2026` per ricorrenze mensili), per distinguere le occorrenze in estratto conto e lista transazioni.

### 6. Service Layer

Il `RecurringTransactionService` centralizza tutta la logica di generazione:

**Metodi principali:**
- `generateTransactionsUntil()`: Genera tutte le transazioni fino a una data target
- `generateNextTransaction()`: Genera solo la prossima transazione
- `calculateNextDueDate()`: Calcola la prossima data di scadenza
- `isActive()`: Verifica se una ricorrenza è ancora attiva
- `processAllRecurringTransactions()`: Processa tutte le ricorrenze attive

## Frequenze Supportate

- **daily**: Giornaliera
- **weekly**: Settimanale
- **monthly**: Mensile
- **yearly**: Annuale

## Logica di Generazione

### Regole di Generazione

1. **Data di inizio (`start_date`)**:
   - Le transazioni vengono generate a partire da questa data
   - Se la data è nel passato, vengono create tutte le occorrenze fino a oggi

2. **Data di fine (`end_date`)** (opzionale):
   - Se presente, limita la generazione a questa data
   - Se è nel passato, la ricorrenza viene considerata non più attiva

3. **Ultima generazione (`last_generated_date`)**:
   - Se presente, le nuove transazioni partono dal giorno successivo
   - Previene duplicazioni in caso di esecuzioni multiple

### Prevenzione Duplicazioni

Il sistema implementa **tre livelli di protezione** contro i duplicati:

#### 1. Controllo Database
Prima di creare ogni transazione, verifica che non esista già:

```php
$exists = Transaction::where('recurring_transaction_id', $recurringTransaction->id)
    ->whereDate('date', $currentDate->toDateString())
    ->exists();
```

#### 2. Sincronizzazione Automatica
All'inizio di ogni generazione, sincronizza `last_generated_date` con l'ultima transazione effettivamente presente nel database:

```php
// Trova l'ultima transazione generata
$lastTransaction = Transaction::where('recurring_transaction_id', $recurringTransaction->id)
    ->orderBy('date', 'desc')
    ->first();

// Aggiorna last_generated_date se necessario
if ($lastTransaction) {
    $recurringTransaction->last_generated_date = $lastTransaction->date;
}
```

Questo garantisce che anche se il comando viene eseguito più volte consecutivamente, non vengano create duplicazioni.

#### 3. Refresh del Model
Prima di ogni generazione, il model viene ricaricato dal database per avere i dati più aggiornati:

```php
$recurringTransaction->refresh();
```

**Test di sicurezza:**
```bash
# Eseguire il comando più volte non crea duplicati
php artisan recurring:generate
php artisan recurring:generate
php artisan recurring:generate

# Risultato: solo le transazioni mancanti vengono create
```

### Gestione dei Saldi

Ogni transazione generata aggiorna automaticamente il saldo del conto:

```php
$account->current_balance += (float) $recurringTransaction->amount;
$account->save();
```

## Utilizzo nel Controller

### Creazione di una Ricorrenza

```php
// Nel metodo store()
$recurringTransaction = RecurringTransaction::create([...]);

// Genera automaticamente tutte le transazioni fino a oggi
$count = $this->recurringService->generateTransactionsUntil($recurringTransaction);

// Feedback all'utente
$message = $count > 0 
    ? "Transazione ricorrente creata con successo. Generate {$count} transazioni."
    : 'Transazione ricorrente creata con successo.';
```

### Generazione Manuale

L'utente può generare manualmente la prossima transazione:

```php
// Nel metodo generate()
$transaction = $this->recurringService->generateNextTransaction($recurringTransaction);
```

## Scheduling

Per attivare lo scheduling di Laravel, è necessario configurare un cron job sul server:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Questo eseguirà il comando `recurring:generate` automaticamente ogni giorno alle 00:01.

## Logging

Il service registra tutte le operazioni importanti:

```php
Log::info("Transazioni ricorrenti generate", [
    'recurring_transaction_id' => $recurringTransaction->id,
    'count' => $generatedCount,
    'last_generated_date' => $lastGenerated?->format('Y-m-d'),
]);
```

## Testing

### Test Manuale

1. Creare una ricorrenza con `start_date` nel passato
2. Verificare che vengano generate tutte le transazioni fino a oggi
3. Controllare il saldo dell'account
4. Eseguire manualmente `php artisan recurring:generate`
5. Verificare che non vengano create duplicazioni

### Casi d'Uso Comuni

**Affitto mensile partito 6 mesi fa:**
```php
start_date: '2025-07-01'
end_date: null
frequency: 'monthly'
amount: -800
→ Genera 7 transazioni (luglio 2025 - gennaio 2026)
```

**Stipendio mensile con data di fine:**
```php
start_date: '2025-01-01'
end_date: '2025-12-31'
frequency: 'monthly'
amount: 2500
→ Genera tutte le transazioni dell'anno 2025
```

**Abbonamento settimanale futuro:**
```php
start_date: '2026-02-01' (nel futuro)
frequency: 'weekly'
amount: -15
→ Nessuna transazione generata fino al 01/02/2026
```

## Migration

Il campo `last_generated_date` è stato aggiunto tramite migration:

```php
Schema::table('recurring_transactions', function (Blueprint $table) {
    $table->date('last_generated_date')->nullable()->after('end_date');
});
```

## Considerazioni Future

- Possibilità di notificare l'utente prima della generazione
- Gestione di ricorrenze più complesse (es. "ogni 2 settimane", "ultimo giorno del mese")
- Dashboard per visualizzare le prossime transazioni ricorrenti
- Possibilità di modificare transazioni già generate mantenendo il link alla ricorrenza
- Gestione di eccezioni (es. saltare una specifica occorrenza)
