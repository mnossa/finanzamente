# Collegamento Transazioni/Ricorrenze a Debiti e Gestione Interessi

## Panoramica
Questa funzionalità permette agli utenti di collegare transazioni (singole o ricorrenti) a debiti/crediti esistenti, in modo che gli importi pagati scalino automaticamente il saldo del debito. Include anche il supporto per il calcolo degli interessi sui debiti.

## Data Implementazione
**20 Febbraio 2026**

## Componenti Modificati

### Database
#### Nuove Tabelle/Colonne

**Tabella `debts_credits`** - Nuovi campi:
- `initial_amount` (decimal): Importo iniziale del debito/credito per tracciare l'ammontare originale
- `paid_amount` (decimal): Importo totale pagato fino a ora
- `interest_rate` (decimal): Tasso di interesse annuale (percentuale)
- `interest_type` (enum): Tipo di interesse - `simple` (semplice) o `compound` (composto)
- `interest_calculation_date` (date): Data di riferimento per il calcolo degli interessi

**Tabella `transactions`** - Nuovi campi:
- `debt_credit_id` (foreign key): Collegamento al debito/credito associato

**Tabella `recurring_transactions`** - Nuovi campi:
- `debt_credit_id` (foreign key): Collegamento al debito/credito associato

### Modelli

#### DebtCredit
Nuovi metodi aggiunti:
- `getRemainingAmount()`: Calcola il saldo rimanente (initial_amount - paid_amount)
- `calculateAccruedInterest($toDate = null)`: Calcola gli interessi maturati fino alla data specificata
- `getTotalAmountWithInterest($toDate = null)`: Calcola l'importo totale comprensivo di interessi
- `recordPayment($paymentAmount)`: Registra un pagamento e aggiorna lo stato del debito
- `isOverdue()`: Verifica se il debito è scaduto
- `isPaid()`: Verifica se il debito è completamente pagato
- `transactions()`: Relazione con le transazioni associate
- `recurringTransactions()`: Relazione con le transazioni ricorrenti associate

#### Transaction
Nuovi metodi aggiunti:
- `debtCredit()`: Relazione con il debito/credito associato
- `isDebtPayment()`: Verifica se la transazione è associata a un debito/credito

#### RecurringTransaction
Nuovi metodi aggiunti:
- `debtCredit()`: Relazione con il debito/credito associato
- `isDebtPayment()`: Verifica se la transazione ricorrente è associata a un debito/credito

### Listener
**UpdateDebtCreditBalance**
- Listener che ascolta l'evento `ModelChanged` per le transazioni
- Aggiorna automaticamente il campo `paid_amount` del debito quando:
  - Viene creata una nuova transazione collegata
  - Viene aggiornata una transazione collegata
  - Viene eliminata una transazione collegata
- Aggiorna lo stato del debito (open/closed/overdue) in base al saldo rimanente e alla data di scadenza

### Service
**RecurringTransactionService**
- Aggiornato per passare `debt_credit_id` alle transazioni generate automaticamente
- Permette che i pagamenti ricorrenti di un debito vengano correttamente tracciati

### Request Validations
Aggiornati i seguenti request per includere la validazione di `debt_credit_id`:
- `StoreTransactionRequest`
- `UpdateTransactionRequest`
- `StoreRecurringTransactionRequest`
- `UpdateRecurringTransactionRequest`

La validazione garantisce che:
- Il debito/credito esista
- Appartenga alla household dell'utente
- Sia in stato `open` o `overdue` (non `closed`)

Aggiornati anche i request per debiti/crediti:
- `StoreDebtCreditRequest`
- `UpdateDebtCreditRequest`

Per includere la validazione dei nuovi campi degli interessi.

### Controller
#### TransactionController
- Metodo `create()`: Include la lista dei debiti/crediti aperti disponibili
- Metodo `store()`: Gestisce il salvataggio di `debt_credit_id`
- Metodo `edit()`: Include la lista dei debiti/crediti aperti e il debito corrente
- Metodo `update()`: Gestisce l'aggiornamento di `debt_credit_id`

#### RecurringTransactionController
- Metodo `create()`: Include la lista dei debiti/crediti aperti disponibili
- Metodo `store()`: Gestisce il salvataggio di `debt_credit_id`
- Metodo `edit()`: Include la lista dei debiti/crediti aperti e il debito corrente
- Metodo `update()`: Gestisce l'aggiornamento di `debt_credit_id`

#### DebtCreditController
- Metodo `index()`: Include i nuovi campi nell'elenco dei debiti
- Metodo `store()`: Gestisce i nuovi campi degli interessi
- Metodo `show()`: Mostra gli interessi maturati e il totale con interessi
- Metodo `edit()`: Include i campi degli interessi nel form di modifica

## Funzionalità

### 1. Collegamento Transazioni a Debiti
Gli utenti possono ora:
- Selezionare un debito/credito quando creano o modificano una transazione
- Vedere solo i debiti/crediti aperti o scaduti (non quelli già chiusi)
- Collegare transazioni ricorrenti a debiti per pagamenti automatici periodici

### 2. Aggiornamento Automatico Saldo
Quando una transazione collegata a un debito viene:
- **Creata**: Il campo `paid_amount` del debito viene incrementato dell'importo pagato
- **Eliminata**: Il campo `paid_amount` del debito viene decrementato dell'importo cancellato
- **Modificata**: Il campo `paid_amount` viene ricalcolato sommando tutte le transazioni attive

Lo stato del debito viene aggiornato automaticamente:
- `closed`: Se il saldo rimanente è ≤ 0.01 (tolleranza arrotondamenti)
- `overdue`: Se la data di scadenza è passata e il debito non è chiuso
- `open`: In tutti gli altri casi

### 3. Calcolo Interessi
I debiti possono avere un tasso di interesse:
- **Interesse Semplice**: Calcolato come `capitale × tasso × (giorni/365)`
- **Interesse Composto**: Calcolato come `capitale × ((1 + tasso/365)^giorni - 1)`

Gli interessi:
- Vengono calcolati sul saldo rimanente (non sull'importo iniziale)
- Partono dalla `interest_calculation_date` (default: data di creazione)
- Possono essere visualizzati nella pagina di dettaglio del debito

### 4. Transazioni Ricorrenti
Le transazioni generate automaticamente da una ricorrenza:
- Ereditano il campo `debt_credit_id` dalla ricorrenza
- Aggiornano automaticamente il saldo del debito ogni volta che vengono generate
- Permettono pagamenti automatici periodici (es. rate mensili)

## Esempi di Utilizzo

### Caso 1: Pagamento Rate Mensili di un Prestito
1. Utente crea un debito di €10.000 con tasso interesse del 5% annuo
2. Crea una transazione ricorrente mensile di €500 collegata al debito
3. Il sistema genera automaticamente le transazioni mensili
4. Ad ogni transazione generata, il saldo del debito diminuisce
5. Dopo 20 mesi, il debito viene automaticamente marcato come "chiuso"

### Caso 2: Debito con Interessi
1. Utente crea un debito di €5.000 con interesse semplice del 8% annuo
2. Dopo 6 mesi, gli interessi maturati sono circa €200
3. Il totale da pagare (visibile nel dettaglio) è €5.200
4. L'utente può pagare rate parziali e vedere sempre il saldo aggiornato con interessi

## Test
Sono stati implementati test completi per tutte le nuove funzionalità:

### Unit Test (DebtCreditTest.php)
- Calcolo saldo rimanente
- Calcolo interessi semplici e composti
- Registrazione pagamenti e aggiornamento stato
- Verifica debiti scaduti e pagati
- Relazioni con transazioni

### Feature Test (DebtCreditTransactionLinkTest.php)
- Aggiornamento automatico saldo alla creazione transazione
- Chiusura automatica debito quando completamente pagato
- Aggiornamento saldo alla cancellazione transazione
- Gestione debiti scaduti
- Gestione transazioni ricorrenti con debiti
- Verifica metodi helper `isDebtPayment()`

## Migration
Le migration sono numerate sequenzialmente:
- `2026_02_20_000001_add_interest_and_payment_tracking_to_debts_credits_table.php`
- `2026_02_20_000002_add_debt_credit_id_to_transactions_table.php`
- `2026_02_20_000003_add_debt_credit_id_to_recurring_transactions_table.php`

La prima migration popola automaticamente `initial_amount` con il valore di `amount` per i record esistenti.

## Considerazioni Tecniche

### Performance
- L'aggiornamento del saldo utilizza `lockForUpdate()` per evitare race condition
- Il calcolo viene fatto sommando tutte le transazioni attive (non soft-deleted)
- Gli interessi vengono calcolati on-demand, non salvati nel database

### Sicurezza
- La validazione garantisce che gli utenti possano collegare solo debiti della propria household
- Non è possibile collegare transazioni a debiti già chiusi
- Il sistema previene inconsistenze usando transazioni database

### Scalabilità
- Per debiti con molte transazioni, il ricalcolo potrebbe essere ottimizzato con cache
- Il sistema di eventi permette facile estensione per notifiche o analytics

## Limitazioni Attuali
- Gli interessi sono calcolati ma non vengono automaticamente aggiunti al saldo del debito
- Non c'è un sistema di notifica quando il debito è in scadenza
- Non è possibile suddividere un pagamento tra più debiti nella stessa transazione

## Possibili Estensioni Future
- Dashboard dedicata per visualizzare i debiti con scadenze imminenti
- Grafici dell'andamento del debito nel tempo
- Notifiche automatiche per rate in scadenza
- Report dettagliati dei pagamenti effettuati
- Possibilità di rifinanziare un debito (creare un nuovo debito che chiude il vecchio)
- Supporto per ammortamenti personalizzati (italiano, francese, tedesco)
- Integrazione con calendario per reminder pagamenti

## Note per gli Sviluppatori
- Il listener `UpdateDebtCreditBalance` è registrato in `EventServiceProvider`
- Il trait `DispatchesModelEvents` deve essere presente sui modelli per il sistema di eventi
- I metodi di calcolo degli interessi sono nel modello `DebtCredit` per centralizzare la logica
- Ricordarsi di aggiornare anche i form React/Inertia quando si modificano i controller

## Riferimenti
- Branch: `ricorrenza_debito`
- Commit principale: vedere git log per i dettagli
- Pull Request: da creare
