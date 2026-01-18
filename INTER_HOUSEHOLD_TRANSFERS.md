# Trasferimenti Inter-Household

## Panoramica

La funzionalità di **trasferimenti inter-household** consente agli utenti di trasferire fondi tra account appartenenti alle proprie households diverse. Questa feature è stata progettata per semplificare la gestione finanziaria personale quando un utente gestisce più gruppi familiari o organizzativi.

**Nota importante**: Dato che l'applicazione è uno strumento di gestione finanziaria (non effettua pagamenti reali), i trasferimenti sono limitati alle sole households dell'utente e vengono completati automaticamente senza necessità di approvazione.

## Caratteristiche Principali

### 1. Trasferimenti Semplificati

- **Solo tra le tue households**: Puoi trasferire solo tra households di cui sei membro
- **Creazione immediata**: Le transazioni vengono create automaticamente al momento del trasferimento
- **Nessuna approvazione richiesta**: Non serve conferma dalla household destinataria
- **Tracciabilità completa**: Ogni trasferimento è registrato con tutti i dettagli

### 2. Stati del Trasferimento

| Stato | Descrizione | Note |
|-------|-------------|------|
| `approved` | Completato con successo | Unico stato utilizzato, le transazioni sono già create |

**Stati rimossi** (non più necessari):
- ~~`pending`~~ - Non serve più l'attesa di approvazione
- ~~`rejected`~~ - Non applicabile tra proprie households
- ~~`cancelled`~~ - Non necessario con creazione immediata

### 3. Gestione Valute Multiple

- Supporto per trasferimenti tra account con valute diverse
- Campo `exchange_rate` per specificare il tasso di cambio
- Calcolo automatico dell'importo di destinazione basato sul tasso di cambio
- Gestione commissioni (`fee`) opzionale

### 4. Sicurezza e Autorizzazioni

#### Policy di Accesso

**Visualizzazione**:
- Un utente può visualizzare un trasferimento se appartiene alla household sorgente o destinataria

**Creazione**:
- L'utente deve appartenere sia alla household dell'account sorgente che a quella destinataria
- Gli account devono appartenere a households diverse dello stesso utente

**Eliminazione**:
- L'utente creatore può eliminare il trasferimento
- Le transazioni collegate vengono eliminate automaticamente (soft delete)

## Architettura

### Database

**Tabella**: `inter_household_transfers`

Campi principali:
- Riferimenti alle households e agli account (sorgente e destinazione)
- Importi e valute (sorgente e destinazione)
- Tasso di cambio e commissioni
- Stato e timestamp delle azioni
- Collegamenti alle transazioni create (dopo approvazione)

Indici per performance:
- `source_household_id`
- `dest_household_id`
- `status`
- `transfer_date`

### Modelli

**`InterHouseholdTransfer`** (`app/Models/InterHouseholdTransfer.php`)

Relazioni:
- `sourceHousehold()` - Household sorgente
- `destinationHousehold()` - Household destinataria
- `sourceAccount()` - Account sorgente
- `destinationAccount()` - Account destinatario
- `sourceUser()` - Utente creatore
- `destinationUser()` - Utente destinatario (opzionale)
- `sourceTransaction()` - Transazione di uscita
- `destinationTransaction()` - Transazione di entrata
- `approvedBy()` - Utente che ha approvato
- `rejectedBy()` - Utente che ha rifiutato

Helper methods:
- `isPending()`, `isApproved()`, `isRejected()`, `isCancelled()`
- `canBeApprovedBy(User)`, `canBeRejectedBy(User)`, `canBeCancelledBy(User)`

### Service Layer

**`InterHouseholdTransferService`** (`app/Services/InterHouseholdTransferService.php`)

Metodi principali:
- `createTransfer()` - Crea un nuovo trasferimento
- `approveTransfer()` - Approva e crea le transazioni
- `rejectTransfer()` - Rifiuta il trasferimento
- `cancelTransfer()` - Annulla il trasferimento
- `deleteTransfer()` - Elimina il trasferimento

### Controller

**`InterHouseholdTransferController`** (`app/Http/Controllers/InterHouseholdTransferController.php`)

Rotte:
- `GET /inter-household-transfers` - Lista trasferimenti
- `GET /inter-household-transfers/create` - Form creazione
- `POST /inter-household-transfers` - Salva nuovo trasferimento
- `GET /inter-household-transfers/{id}` - Dettagli trasferimento
- `POST /inter-household-transfers/{id}/approve` - Approva
- `POST /inter-household-transfers/{id}/reject` - Rifiuta
- `POST /inter-household-transfers/{id}/cancel` - Annulla
- `DELETE /inter-household-transfers/{id}` - Elimina

API Helper:
- `GET /households/{id}/accounts` - Ottiene gli account di una household

### Request Validation

**`StoreInterHouseholdTransferRequest`**
- Validazione campi obbligatori e formati
- Verifica che gli account appartengano a households diverse
- Verifica permessi utente sull'account sorgente
- Validazione destinatario se specificato

**`RejectInterHouseholdTransferRequest`**
- Validazione motivo del rifiuto (opzionale, max 500 caratteri)

### Frontend (React/Inertia)

**Pagine**:
1. **Index** (`resources/js/Pages/InterHouseholdTransfers/Index.tsx`)
   - Lista trasferimenti con filtri (stato, direzione)
   - Badge colorati per gli stati
   - Paginazione

2. **Create** (`resources/js/Pages/InterHouseholdTransfers/Create.tsx`)
   - Form creazione con selezione households e account
   - Caricamento dinamico account della household destinataria
   - Gestione cambio valuta con calcolo automatico
   - Info box per spiegare il processo di approvazione

3. **Show** (`resources/js/Pages/InterHouseholdTransfers/Show.tsx`)
   - Dettagli completi del trasferimento
   - Pulsanti azione condizionali in base allo stato e ai permessi
   - Modal per rifiuto con motivo opzionale
   - Collegamenti alle transazioni create (se approvato)

## Flusso di Utilizzo

### 1. Creazione Trasferimento

1. L'utente accede alla pagina di creazione dal menu "Trasf. Households"
2. Seleziona l'account sorgente dalla household attiva
3. Seleziona una delle sue altre households come destinataria
4. Vengono caricati dinamicamente gli account della household destinataria
5. Seleziona l'account destinatario
6. Inserisce l'importo e, se necessario, il tasso di cambio
7. Opzionalmente aggiunge descrizione, note, commissione
8. Invia il form
9. Il sistema:
   - Crea il trasferimento
   - Crea automaticamente la transazione di uscita nell'account sorgente
   - Crea automaticamente la transazione di entrata nell'account destinatario
   - Aggiorna i saldi di entrambi gli account
   - Imposta lo stato come `approved`
   - Reindirizza alla pagina di dettaglio con messaggio di successo

### 2. Visualizzazione

- L'utente può vedere tutti i suoi trasferimenti nella lista
- Può filtrare per direzione (inviati/ricevuti)
- Ogni trasferimento mostra i dettagli completi
- Collegamenti diretti alle transazioni create

### 3. Eliminazione

- L'utente può eliminare un trasferimento dalla pagina di dettaglio
- L'eliminazione rimuove anche le transazioni collegate (soft delete)
- I saldi degli account vengono ricalcolati automaticamente

## Validazioni

### Lato Backend

- Account sorgente e destinatario devono esistere ed essere attivi
- Devono appartenere a households diverse
- L'utente deve avere accesso a **entrambi** gli account (essere membro di entrambe le households)
- Importi devono essere positivi e validi
- Data trasferimento non può essere futura

### Lato Frontend

- Controlli client-side per esperienza utente fluida
- Disabilitazione campi condizionale (es. account destinatario fino a selezione household)
- Calcolo automatico importo destinazione se presente tasso di cambio

## Considerazioni di Sicurezza

1. **Autorizzazione**: Ogni azione è protetta da policy specifiche
2. **Validazione**: Doppia validazione (frontend + backend)
3. **Integrità Transazionale**: Uso di `DB::transaction()` per garantire atomicità
4. **Soft Delete**: Le transazioni eliminate mantengono traccia storica
5. **Audit Trail**: Registrazione di chi/quando ha approvato/rifiutato

## Estensioni Future

- [ ] Sistema di notifiche push/email per approvazioni
- [ ] Dashboard con statistiche trasferimenti inter-household
- [ ] Export/Report dei trasferimenti
- [ ] API RESTful per integrazioni esterne
- [ ] Supporto allegati (ricevute, screenshot)
- [ ] Sistema di commenti/discussione sul trasferimento
- [ ] Workflow di approvazione multi-step (es. doppia firma)
- [ ] Limiti di importo personalizzabili per household
- [ ] Storico revisioni modifiche pre-approvazione

## Testing

### Test Unitari

- Modello: Test relazioni, helper methods, scopes
- Service: Test logica di business per ogni metodo
- Policy: Test autorizzazioni per ogni azione

### Test Feature

- Creazione trasferimento con dati validi/invalidi
- Approvazione/rifiuto/annullamento con permessi corretti/errati
- Verifica creazione transazioni dopo approvazione
- Verifica aggiornamento saldi account
- Test soft delete e cascade

### Test E2E (Da implementare)

- Flusso completo: creazione → approvazione → verifica transazioni
- Flusso rifiuto con motivo
- Flusso annullamento

## Manutenzione

### Migrazioni

- File: `database/migrations/2026_01_18_000001_create_inter_household_transfers_table.php`
- Eseguita con successo il: 18/01/2026

### Indici Database

Gli indici sono già configurati per ottimizzare le query più comuni:
- Ricerca per household (sorgente/destinazione)
- Filtro per stato
- Ordinamento per data trasferimento

### Monitoring

Metriche da monitorare:
- Tempo medio approvazione trasferimenti
- Percentuale rifiuti vs approvazioni
- Volume trasferimenti per household
- Errori/fallimenti nella creazione transazioni

---

**Documentazione creata il**: 18/01/2026  
**Versione**: 1.0  
**Autore**: Sistema Finanzamente
