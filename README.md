
# Finanzamente - Documentazione Progetto

## Descrizione Generale
Finanzamente è una webapp di gestione finanziaria personale e familiare, pensata per utenti residenti in Italia tra i 18 e i 45 anni. L’applicazione è mobile first, full responsive, in lingua italiana, e offre strumenti avanzati per la gestione di finanze, investimenti, budgeting, household multipli e privacy.

## Aree Funzionali

### 1. Gestione Household/Famiglia
- Creazione e gestione di household (famiglie, gruppi, team)
- Invito e gestione membri con ruoli e permessi granulari (owner, member, guest, supervise, private)
- Supervisione familiare: il genitore può vedere e monitorare le finanze del figlio, che mantiene aree private

### 2. Conti e Transazioni
- Gestione di conti bancari, carte, contanti, wallet crypto
- Transazioni categorizzate, ricorrenti, taggate, con allegati multipli
- Possibilità di rendere conti e transazioni privati o condivisi

### 3. Investimenti
- Supporto per crypto, azioni, ETF, indici, materie prime, assicurazioni
- Gestione di acquisti, vendite, movimenti, allegati e privacy

### 4. Budgeting e Obiettivi Finanziari
- Definizione di budget per categorie, household e periodo
- Monitoraggio avanzato e alert
- Gestione obiettivi finanziari personali e di gruppo

### 5. Debiti e Crediti
- Gestione di prestiti, debiti e crediti tra utenti, household o con terzi
- Tracciamento stato, scadenze, pagamenti e notifiche

### 6. Notifiche e Log
- Sistema di notifiche per utenti e household
- Log accessi, modifiche e audit per sicurezza e trasparenza

### 7. Tagging e Allegati
- Associazione di tag e allegati multipli a transazioni, investimenti, documenti

### 8. Privacy e Supervisione
- Policy granulari per la visibilità dei dati
- Flag privacy su ogni entità (conti, transazioni, investimenti)
- Audit log delle azioni di supervisione

### 9. Spese Condivise
- Gestione di spese condivise tra più utenti
- Suddivisione automatica e tracciamento pagamenti

### 10. Consensi e GDPR
- Gestione consensi privacy, policy di data retention e cancellazione

## Flussi Utente Principali
- Registrazione e validazione email
- Creazione/adesione a household
- Gestione conti, transazioni, investimenti
- Impostazione privacy e permessi
- Supervisione familiare e audit
- Gestione notifiche e consensi

## Struttura delle Entità Principali
- users, households, household_user, accounts, categories, transactions, investments, budgets, debts_credits, financial_goals, notifications, tags, attachments, access_logs, investment_assets

## Architettura Tecnologica (vedi copilot-instructions.md per dettagli tecnici)
- Backend: Laravel (PHP), API RESTful, sicurezza, migrazioni, validazione
- Frontend autenticato: React + Inertia.js, TypeScript
- Frontend pubblico/SSR: Blade
- Database: MySQL, tabelle in inglese, espandibile

## Accessibilità e Best Practice
- Mobile first, UI/UX reattiva e accessibile (WCAG 2.1)
- Codice DRY/KISS, componenti riutilizzabili, linting e formattazione
- Testing automatico, CI/CD, documentazione aggiornata
- Gestione privacy, consensi, GDPR, backup e disaster recovery
- Logging, monitoraggio, analytics privacy-friendly

## Aggiornamento e Estendibilità
- La documentazione va aggiornata ad ogni evoluzione architetturale o funzionale
- Il sistema è progettato per supportare nuove funzionalità (reportistica, automazioni, integrazioni, ecc.)

---

## Trasferimenti: contratto Frontend → Backend

Il backend calcola `dest_amount` in modo autoritativo. Il frontend non deve inviarlo, ma può mostrare una stima locale.

- Endpoint: `POST /transfers`
- Richiesta (senza `dest_amount`):
	- `source_account_id` (number, required)
	- `destination_account_id` (number, required)
	- `source_amount` (number, required, valore assoluto)
	- `source_currency` (string, required, es. `EUR`)
	- `dest_currency` (string, required)
	- `exchange_rate` (number, required se valuta diversa; altrimenti opzionale)
	- `source_category_id` (number, required, tipo `expense`)
	- `dest_category_id` (number, required, tipo `income`)
	- `fee` (number, optional, valore assoluto, addebito su conto sorgente)
	- `fee_category_id` (number, optional; se assente verrà creata/riutilizzata la categoria "Fee")
	- `date` (string, optional, ISO `YYYY-MM-DD`)
	- `description` (string, optional)
	- `is_private` (boolean, optional)

Risposta: include il `transfer` con `dest_amount` calcolato lato server e le transazioni collegate.

### Stima locale `dest_amount`

Disponibili helper JS in `resources/js/transfers/estimate.js` ed esposti globalmente come `window.Transfers`:

```js
const estimate = window.Transfers.estimateDestAmount({
	sourceAmount: 123.45,
	exchangeRate: 1.10234,
	sourceCurrency: 'EUR',
	destCurrency: 'USD',
});
// Mostra la stringa stima (arrotondata a 8 decimali)

const payload = window.Transfers.buildTransferPayload({
	source_account_id: 1,
	destination_account_id: 2,
	source_amount: 123.45,
	source_currency: 'EUR',
	dest_currency: 'USD',
	exchange_rate: 1.10234,
	source_category_id: 10, // expense
	dest_category_id: 11,   // income
	fee: 0.5, // opzionale
});

window.Transfers.submitTransfer(payload).then(console.log);
```

Regole di calcolo della stima:
- Stessa valuta: `dest = source_amount`.
- Valute diverse: `dest = round(source_amount * exchange_rate, 8)`.

## Permessi e Ownership (Docker)

Per evitare file creati con utente `root` (es. `public/hot`) i container `app` e `node` usano le variabili `LOCAL_UID` e `LOCAL_GID`.

### Avvio consigliato

```bash
make up        # avvia tutto con UID/GID correnti
make dev       # avvia Vite nel container node
make fix-perms # riallinea i permessi in caso di problemi
```

Il file `docker-compose.yml` usa `user: "${LOCAL_UID:-1000}:${LOCAL_GID:-1000}"`. Se non passi le variabili saranno usati i default `1000:1000`.

### Reimpostare manualmente

```bash
export LOCAL_UID=$(id -u)
export LOCAL_GID=$(id -g)
docker compose down && docker compose up -d
```

### Script Permessi

Lo script `fix-permissions.sh` esegue:
- chown ricorsivo al progetto
- imposta permessi 775 su `storage` e `bootstrap/cache`
- rimuove `public/hot` se bloccato

Usare dopo anomalie di ownership:

```bash
make fix-perms
```

> Per linee guida tecniche, convenzioni di sviluppo e best practice, consultare il file `.github/copilot-instructions.md`.