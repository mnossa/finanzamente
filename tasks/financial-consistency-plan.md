# Piano: coerenza calcoli finanziari (tracker spese/investimenti)

> Audit: 2026-06-05 · Test di riferimento: `tests/Feature/FinancialConsistencyTest.php`

## Scopo del prodotto (north star)

Finanzamente deve aiutare l’utente a **capire dove vanno i soldi** (spese correnti vs investimenti vs risparmio) e a **decidere consapevolmente**, non a mostrare numeri gonfiati o contraddittori tra sezioni.

**Principio guida:** un solo ledger operativo (transazioni sui conti) + registro investimenti collegato; ogni schermata dichiara cosa misura.

---

## Modello dati target

| Concetto | Fonte canonica | Cosa mostra |
|----------|----------------|-------------|
| **Saldo conti / cash** | `initial_balance + Σ transazioni` | Liquidità reale spendibile |
| **Investito (posizioni)** | `Investment` aperti (cost basis ± NAV) | Quanto è allocato in strumenti |
| **Patrimonio netto** | cash + investimenti (vista patrimoniale) | `/patrimonio`, allocazione asset |
| **Spese di consumo (50/30/20, lifestyle)** | Transazioni classificate + investimenti senza tx | Quanto “esci” dal consumo vs investi |
| **Andamento nel tempo** | Serie coerente con la metrica scelta | Cashflow vs patrimonio |

---

## Incongruenze rilevate

### 🔴 Critiche (confondono l’utente)

| # | Problema | Dove | Impatto |
|---|----------|------|---------|
| C1 | **Patrimonio somma liquidità + investito** anche quando l’investimento non ha scalato il conto (PAC senza conto / non sincronizzato) | `PortfolioSnapshotService::build()` | Patrimonio totale gonfiato: es. 10k conto + 10k PAC = 20k |
| C2 | **Etichetta “Patrimonio Netto”** su grafici che tracciano **solo il ledger cash** | `DashboardController::getNetWorthData()`, `DashboardAnalyticsService::getNetWorthSeries()` | Utente crede di vedere patrimonio completo; dopo acquisto ETF il grafico scende ma non mostra il controvalore investito |
| C3 | **Saldo totale dashboard vs pagina Conti** possono divergere | Dashboard ricalcola da tx; Conti usa `accounts.current_balance`; listener `UpdateAccountBalance` usa logica categoria diversa dal raw sum | Due “verità” sullo stesso saldo |

### 🟠 Medie (metriche non allineate allo scopo tracker)

| # | Problema | Dove | Impatto |
|---|----------|------|---------|
| M1 | **Entrate/uscite 30 gg** contano i trasferimenti interni come entrata+uscita | `DashboardController::getPeriodStats()` | KPI “Uscite” e “Entrate” fuorvianti; net ok ma trend ingannevole |
| M2 | **50/30/20** non esclude `transfer_id` | `getExpenseDistributionData()` | Trasferimento tra conti può finire in needs/wants |
| M3 | **PAC senza conto** entra nel 50/30/20 (investimenti) ma **non** nel Lifestyle Score | expense distribution vs `FinancialMetricsService` | Due viste del risparmio/investimento non allineate |
| M4 | **Conti broker** inclusi nel saldo dashboard ma esclusi dalla liquidità Patrimonio | Dashboard vs `PortfolioSnapshotService` | Stesso “saldo totale” ≠ stesso scope conti |
| M5 | **Commissioni**: transazione sync `- (costo + fees)`; `investedValue` usa solo `quantity × buy_price` | sync vs snapshot | “Di cui investimenti” ≠ importo uscito dal conto |
| M6 | **Investimenti index** (prezzo mercato) vs **Patrimonio/allocazione** (cost basis) | `InvestmentMetricsService` vs `PortfolioSnapshotService` | Stessi asset, valori diversi senza spiegazione in UI |

### 🟡 Basse (UX / accesso)

| # | Problema | Dove |
|---|----------|------|
| B1 | Widget Lifestyle richiede 2 mesi; pagina `/punteggio-stile-vita` no | `getLifestyleWidgetData()` vs route |
| B2 | Periodo stats dashboard = rolling 30 gg; 50/30/20 = mese solare | naming “monthlyStats” fuorviante |
| B3 | Cashflow widget non esclude trasferimenti | `getCashFlowData()` |

---

## Test aggiunti (baseline attuale)

File: `tests/Feature/FinancialConsistencyTest.php`

| Test | Cosa verifica |
|------|----------------|
| `dashboard_total_is_ledger_only...` | Saldo ≠ liquid + investito addizionato |
| `synced_investment_reduces_cash...` | Acquisto sync scala cash + breakdown |
| `expense_distribution_does_not_double_count...` | 50/30/20 senza doppio conteggio sync |
| `lifestyle_excludes_synced_investment...` | Allineamento lifestyle vs bucket investimenti (sync) |
| `unsynced_investment_affects_expense_distribution_but_not_lifestyle` | **Gap M3 documentato** |
| `patrimonio_total_is_liquid_plus_invested...` | Patrimonio ≠ saldo dashboard (by design oggi) |
| `broker_cash_in_dashboard_total...` | **Gap M4 documentato** |
| `net_worth_series_tracks_cash_ledger...` | **Gap C2 documentato** |
| `period_stats_count_internal_transfers...` | **Gap M1 documentato** |
| `accounts_index_total_uses_stored_balance...` | Coerenza Conti/Dashboard quando listener ok |

---

## Piano di implementazione

### Fase 1 — Chiarezza semantica (quick wins, 1–2 PR)

**Obiettivo:** stessi numeri, etichette honest; niente doppio conteggio percepito.

1. **Rinominare e documentare in UI**
   - Widget saldo: titolo “Saldo conti” (o sottotitolo “Somma saldi conti attivi”)
   - Grafico: “Liquidità nel tempo” invece di “Patrimonio Netto” finché non include investimenti
   - Patrimonio: hero “Patrimonio totale” con tooltip “Conti + posizioni investimento (costo)”
   - Investimenti index: badge “Valore di mercato” vs Patrimonio “Costo di carico”

2. **Allineare `invested` nel breakdown**
   - Includere fees in `investedValue` o mostrare “Di cui investimenti (costo)” e “Uscite investimenti (conto)” separati
   - File: `PortfolioSnapshotService`, `DashboardController`

3. **Copy PAC senza conto**
   - Banner in creazione/modifica PAC: “Senza conto collegato non impatta saldo e transazioni; compare solo in Investimenti e 50/30/20”

**Test:** aggiornare assert su label Inertia se esposte; mantenere `FinancialConsistencyTest`.

---

### Fase 2 — Unificare le fonti di calcolo (core, 1 PR)

**Obiettivo:** un service unico per saldi conto, evitare C3.

1. **`AccountBalanceService`**
   ```php
   computeBalance(Account $account): float  // initial + SUM(amount), stesso metodo ovunque
   computeHouseholdTotal(User $user): float
   ```
2. **Usare in:** `DashboardController`, `AccountController`, `PortfolioSnapshotService`, export PDF
3. **`UpdateAccountBalance` listener:** allineare a raw `SUM(amount)` (amount già firmato in insert) **oppure** deprecare doppio update in `TransactionController` (`+= amount`)

**Test:** 
- `AccountBalanceServiceTest` (unit)
- `FinancialConsistencyTest`: dopo create/update/delete tx, dashboard = accounts index

---

### Fase 3 — Investimenti ↔ ledger (tracker integrity, 1–2 PR)

**Obiettivo:** ogni acquisto investimento ha effetto tracciabile coerente.

1. **Patrimonio senza double-count (C1)**
   - Opzione A (consigliata): `totalValue` patrimonio = `max(liquid, 0) + invested` dove liquid **non** include già uscite investimento non registrate; per unsynced: mostrare invested separato e **non** sommare a total se non c’è uscita conto
   - Opzione B: obbligare `account_id` su PAC (soft warning → hard per Pro)
   - Opzione C: “virtual outflow” in snapshot solo per display breakdown, non in total

2. **Sync investimenti ↔ transazioni (solo backend, invisibile all’utente)**
   - Automatico: `InvestmentObserver` alla creazione/modifica con conto
   - Backfill storico **one-shot**: migration `2026_06_05_120000_backfill_investment_pac_ledger_links` (realign + sync), **non** entrypoint a ogni deploy
   - **Mai** banner, tooltip o istruzioni CLI in UI per utenti finali

3. **Lifestyle + 50/30/20 allineati (M3)**
   - Estendere `FinancialMetricsService`: `excluded_expenses` include anche acquisti `Investment` del periodo senza tx (stessa logica expense distribution)
   - Oppure: generare sempre transazione virtuale in metriche (no DB) per coerenza

**Test:**
- Patrimonio unsynced: total ≠ liquid + invested gonfiato (fix assert)
- Lifestyle con PAC no account: excluded = importo PAC

---

### Fase 4 — Metriche spesa/risparmio (1 PR)

**Obiettivo:** KPI dashboard utili per decisioni, non rumore.

1. **`getPeriodStats` / cashflow**
   - `whereNull('transfer_id')` come lifestyle
   - Opzionale: bucket “Trasferimenti” separato in UI

2. **`getExpenseDistributionData`**
   - Escludere `transfer_id`
   - Escludere transazioni con `investment_id` già conteggiate? (già dedupe via categoria)

3. **Periodi**
   - Rinominare prop `monthlyStats` → `periodStats` (breaking frontend)
   - Tooltip “Ultimi 30 giorni” vs widget 50/30/20 “Mese corrente”

**Test:** `FinancialConsistencyTest::period_stats_*` → assert income/expenses 0 su trasferimento; nuovo test expense distribution ignora transfer

---

### Fase 5 — Patrimonio nel tempo (1 PR)

**Obiettivo:** grafico utile per tracker investimenti.

1. **`NetWorthSeriesService`** con modalità:
   - `cash` (oggi)
   - `portfolio` (liquid + invested cost basis, storico mensile)
2. **Storico investimenti:** snapshot mensile a fine mese (job o calcolo on-the-fly da `Investment.buy_date` + tx)
3. Dashboard widget: toggle o default `portfolio` con legenda

**Test:** dopo acquisto sync, ultimo punto portfolio ≈ liquid + invested; cash point = solo liquid

---

### Fase 6 — Broker e multi-conto (opzionale)

1. Policy esplicita: broker cash in liquidità patrimonio **sì/no** (config household?)
2. Allineare dashboard total scope con patrimonio (includere o escludere broker ovunque)

---

## Priorità consigliata

```
Fase 1 (copy + fees)  →  Fase 2 (balance service)  →  Fase 3 (investimenti/ledger)
        ↓
Fase 4 (transfer filter)  →  Fase 5 (grafico patrimonio reale)
```

---

## Definition of done (per fase)

- [x] Test in `FinancialConsistencyTest` verdi con comportamento **target**
- [ ] `make test`, `make pint-check`, E2E smoke dashboard + investimenti + patrimonio
- [x] Testi UI italiani coerenti con formula sotto il numero
- [x] Nessun widget mostra “totale” senza indicare cosa include/esclude

### Checklist implementazione (2026-06-05)

- [x] Fase 1: UI labels (Saldo conti, Patrimonio tooltips, valuation note investimenti)
- [x] Fase 1: `investedValue` include commissioni
- [x] Fase 1: Banner PAC senza conto (create/edit)
- [x] Fase 2: `AccountBalanceService` + refactor Dashboard/Accounts/Portfolio
- [x] Fase 2: Fix `UpdateAccountBalance` raw SUM(amount)
- [x] Fase 3: Patrimonio no double-count unsynced (`totalValue = liquid + linked`)
- [x] Fase 3: Lifestyle excluded include unsynced investment purchases
- [x] Fase 3: CTA sync investimenti (dashboard/investimenti/patrimonio) — **rimosso**: sync automatico, no UI tecnica
- [x] Fase 4: Exclude `transfer_id` da period stats, expense dist, cashflow
- [x] Fase 4: `periodStats` rename + UI label 30 gg
- [x] Fase 5: `NetWorthSeriesService` cash + portfolio modes
- [x] Fase 6: Broker incluso in patrimonio liquid (allineato dashboard)
- [x] Verifica CI: `make test`, `make pint-check`, `make playwright`

---

## Riferimenti codice

| Area | File principale |
|------|-----------------|
| Dashboard saldo / 50/30/20 | `app/Http/Controllers/DashboardController.php` |
| Patrimonio snapshot | `app/Services/PortfolioSnapshotService.php` |
| Sync investimenti | `app/Services/InvestmentTransactionSyncService.php` |
| Lifestyle | `app/Services/FinancialMetricsService.php` |
| Grafici analisi | `app/Services/DashboardAnalyticsService.php` |
| Saldo conto stored | `app/Listeners/UpdateAccountBalance.php`, `app/Models/Account.php` |
