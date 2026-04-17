# Test E2E isolati su database MySQL dedicato

Per garantire che i test E2E Playwright e il comando `make e2e-seed` non modifichino mai i dati reali di sviluppo o produzione, viene utilizzato un database MySQL separato (`db_e2e`).

- La connessione E2E è configurata in `config/database.php` come `e2e_mysql` e usa variabili dedicate (`E2E_DB_*`).
- Il file `.env.e2e` contiene tutte le variabili per il database E2E.
- Il servizio Docker `db_e2e` è definito in `docker-compose.yml` e ascolta sulla porta 3307.
- I comandi `make e2e-seed` e `make playwright` esportano automaticamente le variabili per usare il database E2E.

**Nota:** Il database E2E viene svuotato e ricreato ad ogni esecuzione di `make e2e-seed`, senza mai toccare i dati reali.

Per avviare i test E2E in locale:

```
make up           # Avvia tutti i servizi, incluso db_e2e
make e2e-seed     # Prepara il database E2E
make playwright   # Esegue i test Playwright su db_e2e
```

Il database reale (sviluppo/produzione) non viene mai toccato dai test E2E.
#
## Sicurezza e Rate Limiting avanzato

- Tutte le rotte di autenticazione e registrazione sono protette da un middleware di rate limiting avanzato (`AdvancedRateLimitWithDelay`).
- Il middleware applica:
	- Limite di tentativi per IP e route configurabile
	- Delay progressivo per tentativi ripetuti
	- Logging di ogni tentativo su canale `security.log` (solo hash IP, route, timestamp)
	- L’hash dell’IP è calcolato con SHA256 e salt segreto impostato in `.env` tramite `ADV_THROTTLE_SALT` (GDPR compliant)

Esempio di configurazione in `.env`:

		ADV_THROTTLE_SALT=valore-segreto

Per cambiare la sensibilità del rate limiting, modificare i parametri nel middleware o nella definizione delle rotte.

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

## Comandi di Sviluppo

Il progetto utilizza un **Makefile** per semplificare le operazioni comuni di sviluppo Docker.

### Container Management
```bash
make up          # Avvia lo stack Docker
make down        # Ferma lo stack Docker
make restart     # Riavvia lo stack Docker
make logs        # Visualizza i log dei container
make ps          # Mostra lo stato dei container
```

### Accesso ai Container
```bash
make app         # Accesso shell al container PHP/Laravel
make node        # Accesso shell al container Node.js
make mysql-root  # Accesso MySQL come root
```

### Database
```bash
make migrate     # Esegue le migrazioni del database
make fresh       # Reset database (migrate:fresh)
make seed        # Popola il database con i seeder base
```

### Dati Demo
```bash
make demo-data   # Genera dati demo: 2 utenti, 4 household, 16000 transazioni totali
make demo-reset  # Reset completo DB + seeder base + dati demo (richiede conferma)
```

Il comando `make demo-data` genera:
- **2 utenti**: uno con P.IVA (`mario.rossi@example.com`) e uno residenziale (`laura.bianchi@example.com`)
- **4 household**: 2 per ogni utente
- **16.000 transazioni**: 4000 per household, distribuite dal 2022 ad oggi
- **12 debiti/crediti**: 3 per ogni household (uno già pagato)
- **20 transazioni ricorrenti**: 5 per household (affitto, stipendio, bollette, ecc.)
- **16 conti**: 4 per household (banca, contante, carta, risparmio)

Password per tutti gli utenti: `password`

### Testing
```bash
make test        # Esegue l'intera suite di test (usa SQLite in-memory)
```

### Sviluppo Frontend
```bash
make dev         # Avvia il dev server Vite per hot-reload
make clear-cache # Pulisce tutte le cache Laravel (config, route, view)
```

### Utilità
```bash
make fix-perms              # Corregge i permessi dei file del progetto
make exec cmd="comando"     # Esegue un comando personalizzato nel container app
```

**Nota importante**: Usare sempre i comandi `make` per garantire che i container Docker utilizzino l'UID/GID corretto evitando problemi di permessi sui file.

## Testing

### Database di Test

I test utilizzano **SQLite in-memory** invece del database MySQL principale, garantendo:
- **Isolamento completo**: il database di sviluppo non viene mai modificato
- **Velocità**: esecuzione in RAM, senza I/O su disco
- **Pulizia**: ogni test parte da uno stato vuoto grazie al trait `RefreshDatabase`

### Configurazione

Il file [.env.testing](.env.testing) contiene la configurazione specifica per i test:
```ini
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

Questa configurazione sovrascrive le impostazioni del file `.env` durante l'esecuzione dei test.

### Esecuzione dei Test

```bash
make test                    # Esegue l'intera suite di test
make test-auth              # Esegue solo i test di autenticazione
make test-households        # Esegue i test degli household
```

Il comando `make test` esegue `php artisan test` nel container Docker con l'utente corretto (UID/GID).

### Migrazioni e Seeding

Durante i test, Laravel:
1. Crea automaticamente lo schema SQLite in memoria
2. Esegue tutte le migrazioni
3. Esegue il seeding configurato in `TestCase::setUp()` (es. `CurrencySeeder`)
4. Ripulisce tutto automaticamente tra un test e l'altro

**Importante**: Il file `.env.testing` è tracciato in Git per garantire la stessa configurazione a tutti i membri del team.

## Aggiornamento e Estendibilità


## Flusso Git e Protezione Branch

Per garantire stabilità e test approfonditi, il progetto utilizza il branch `staging` come ambiente intermedio prima del rilascio su `main`.

**Workflow consigliato:**
1. Tutte le nuove feature, fix e modifiche vengono sviluppate e testate su branch dedicati (feature/fix), poi merge su `staging`.
2. Il branch `staging` viene testato su ambiente Raspberry Pi o altri ambienti di staging.
3. Solo dopo test superati, si esegue il merge da `staging` a `main`.
4. Il branch `main` rappresenta sempre la versione stabile e pronta per il deploy.

**Best practice:**
- Evitare commit o merge diretti su `main`. Usare solo merge da `staging`.
- Proteggere il branch `main` tramite regole GitHub (vedi sotto).
- Taggare ogni release su `main` (es. `v1.2.3`).
- Documentare eventuali hotfix o merge inversi (main → staging).

**Protezione branch main su GitHub:**
1. Vai su "Settings" del repository → "Branches" → "Branch protection rules".
2. Crea una regola per `main`:
	- Blocca push diretti (richiedi pull request).
	- Richiedi almeno 1 review.
	- Richiedi che i test CI siano passati.
	- (Opzionale) Blocca merge se ci sono conflitti o build fallite.
3. Salva la regola.

Questo garantisce che ogni rilascio passi da test e review, evitando disallineamenti tra `staging` e `main`.

> Per dettagli tecnici e linee guida, consultare `.github/copilot-instructions.md`.

## Integrazione Telegram Bot

Il bot Telegram permette di registrare spese direttamente dalla chat e di ricevere notifiche.

### Configurazione (sviluppo locale con ngrok)

1. Crea il bot tramite [@BotFather](https://t.me/BotFather) e ottieni il token.
2. Aggiungi le variabili al `.env`:
   ```env
   TELEGRAM_BOT_TOKEN=<token-da-botfather>
   TELEGRAM_BOT_USERNAME=<username-senza-@>
   ```
3. Pulisci la cache: `make clear-cache`
4. Esponi il server locale con ngrok:
   ```bash
   ngrok http 8080
   ```
5. Registra il webhook con l'URL ngrok:
   ```bash
   make set-telegram-webhook url=https://abc123.ngrok-free.app
   ```
6. Verifica che il webhook sia attivo:
   ```bash
   make get-telegram-webhook
   ```

### Configurazione in produzione

In produzione (con dominio HTTPS reale) non serve ngrok. Basta eseguire una volta:
```bash
make set-telegram-webhook url=https://tuodominio.it
```
Il webhook rimane attivo permanentemente finché non viene rimosso o aggiornato.

> **Nota:** Telegram richiede che l'URL del webhook sia raggiungibile pubblicamente via HTTPS. Su `localhost` non funziona senza un tunnel.

### Comandi Make disponibili

```bash
make set-telegram-webhook url=https://tuodominio.it  # Registra il webhook
make get-telegram-webhook                             # Mostra lo stato attuale del webhook
```



La dashboard è completamente personalizzabile da ogni utente autenticato. È possibile:

1. **Scegliere i widget visibili** — mostrare o nascondere ogni blocco della dashboard.
2. **Riordinare i widget** — trascinare i widget per cambiarli di posizione (drag & drop).
3. **Ridimensionare i widget** — scegliere tra le dimensioni consentite (`sm`, `md`, `lg`, `xl`).
4. **Salvare la configurazione** — il layout viene persistito nel database per household attiva.
5. **Ripristinare il layout di default** — in qualsiasi momento tramite il pulsante dedicato.

### Attivazione della modalità personalizzazione

Il pulsante **"Personalizza dashboard"** è visibile in alto a destra nella pagina della dashboard. Cliccandolo si entra in modalità modifica:

- Ogni widget mostra una barra di controllo con maniglia drag, selettore dimensione e toggle visibilità.
- Al termine delle modifiche, cliccare **"Salva layout"** per persistere o **"Annulla"** per ripristinare lo stato precedente.
- Il pulsante **"Ripristina default"** elimina la configurazione personalizzata e ripristina il layout di default.

### Struttura dati della configurazione

La configurazione viene salvata nella tabella `dashboard_layouts` con struttura JSON:

```json
{
  "widgets": [
    { "id": "total_balance",      "visible": true,  "position": 0, "size": "lg" },
    { "id": "monthly_stats",      "visible": true,  "position": 1, "size": "lg" },
    { "id": "annual_revenue",     "visible": true,  "position": 2, "size": "lg" },
    { "id": "tax_thermometer",    "visible": true,  "position": 3, "size": "lg" },
    { "id": "lifestyle_widget",   "visible": true,  "position": 4, "size": "lg" },
    { "id": "accounts",           "visible": true,  "position": 5, "size": "md" },
    { "id": "recent_transactions","visible": true,  "position": 6, "size": "md" },
    { "id": "active_budgets",     "visible": true,  "position": 7, "size": "md" },
    { "id": "debts_credits",      "visible": true,  "position": 8, "size": "md" },
    { "id": "quick_actions",      "visible": true,  "position": 9, "size": "lg" }
  ]
}
```

#### Campi del singolo widget

| Campo      | Tipo      | Descrizione                                                  |
|------------|-----------|--------------------------------------------------------------|
| `id`       | `string`  | Identificativo stabile del widget (vedi lista sotto)         |
| `visible`  | `boolean` | Se `false` il widget non viene renderizzato in dashboard     |
| `position` | `integer` | Indice di posizione (usato per ordinare i widget)            |
| `size`     | `string`  | Dimensione griglia: `sm`, `md`, `lg`, `xl`                   |

#### Widget disponibili

| ID                   | Titolo                    | Visibilità condizionale              |
|----------------------|---------------------------|--------------------------------------|
| `total_balance`      | Saldo Totale              | Sempre visibile                      |
| `monthly_stats`      | Statistiche Mensili       | Sempre visibile                      |
| `annual_revenue`     | Fatturato Annuo           | Solo utenti Partita IVA              |
| `tax_thermometer`    | Termometro Tasse          | Solo utenti Partita IVA              |
| `lifestyle_widget`   | Lifestyle Inflation Score | Sempre visibile (sblocco progressivo)|
| `accounts`           | I tuoi Conti              | Sempre visibile                      |
| `recent_transactions`| Ultime Transazioni        | Sempre visibile                      |
| `active_budgets`     | Budget Attivi             | Richiede modulo `budgets`            |
| `debts_credits`      | Debiti e Crediti          | Richiede modulo `debts_credits`      |
| `quick_actions`      | Azioni Rapide             | Sempre visibile                      |

#### Dimensioni griglia

| Valore | Comportamento                                 |
|--------|-----------------------------------------------|
| `sm`   | 1 colonna (metà larghezza su desktop)         |
| `md`   | 1 colonna (metà larghezza su desktop)         |
| `lg`   | 2 colonne su desktop (larghezza intera)       |
| `xl`   | 2 colonne su desktop (larghezza intera)       |

#### Note su griglia responsive

- La griglia usa `grid-cols-2` su schermi `lg` e superiori, `grid-cols-1` su mobile.
- I widget con dimensione `lg` o `xl` occupano entrambe le colonne su desktop (`col-span-2`).
- I widget con dimensione `sm` o `md` occupano una singola colonna.

### API backend

| Metodo   | URL                      | Azione                                   |
|----------|--------------------------|------------------------------------------|
| `GET`    | `/dashboard/layout`      | Legge la configurazione corrente (o default) |
| `POST`   | `/dashboard/layout`      | Salva la configurazione                  |
| `DELETE` | `/dashboard/layout`      | Ripristina il layout di default          |

La configurazione è salvata per coppia `(user_id, household_id)`.

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