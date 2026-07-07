# UX hub nav + menu compatto + mobile sticky bar

Piano derivato da `tasks/financial-consistency-plan.md` (fasi 1–6 già implementate) + richiesta utente.

## 1. Componenti hub (generico)
- [x] `SectionHubNav.tsx` — tab orizzontali, scroll orizzontale
- [x] Refactor `InvestmentHubNav` su `SectionHubNav`
- [x] `CashflowHubNav`, `OrganizationHubNav`, `PlanningHubNav`

## 2. Investimenti — completamento hub
- [x] Hub nav su `InvestmentAssets/Index`, `InvestmentAnalyses/Index`
- [x] `backLink` → `investments.index` su tab secondarie (PAC, Allocazione, Asset, Analisi)

## 3. Hub altre sezioni
- [x] Cashflow: Transazioni, Conti, Trasferimenti, Sessione rapida (se abilitata), Trasf. HH
- [x] Organizzazione: Categorie, Inbox, Rimborsi, Ricorrenti
- [x] Pianificazione: Budget, Debiti, Obiettivi, Spese detraibili

## 4. Sidebar compatta
- [x] Una voce per sezione con `routeMatchPatterns` multipli
- [x] Rimuovere duplicati (es. Asset Allocation sotto Investimenti)
- [x] Sezione a voce singola: niente header collassabile ridondante

## 5. Mobile sticky bar
- [x] `main` padding bottom con safe-area + FAB
- [x] Bottom nav compatto con backdrop blur
- [x] Hub nav non sticky (evita intercettazione click su CTA sottostanti)

## 6. Verifica
- [x] `make test`, `make pint-check`, `make playwright`
- [x] Aggiornare DoD in `financial-consistency-plan.md`

## Review
- Coerenza finanziaria: già coperta da `FinancialConsistencyTest` (942 test PHPUnit verdi).
- Navigazione: hub tab per 4 macro-sezioni; sidebar da ~15 voci a 7 (+ Panoramica 3).
- E2E aggiornati per menu compatto e scroll mobile.

# Inserimento rapido transazioni — riduzione tocchi

## Piano
- [x] Wizard guidato: chip frequente apre percorso breve con tipo/categoria/conto/data già impostati.
- [x] Wizard guidato: dopo importo, CTA salva senza passare da data/conto/categoria/opzioni.
- [x] Form classico: chip scelta mostra riepilogo compatto e comprime campi già precompilati.
- [x] Redesign `TransactionQuickChips`: sezione hero (⚡) con griglia di card, non più lista orizzontale.
- [x] Separatore "oppure inserisci a mano" tra chip e campi manuali (classico + guidato).
- [x] E2E: chip → importo lascia pronti conto/categoria e rende disponibile il salvataggio.
- [x] Verifica: `make test` (verde), `make pint-check` (verde), `make playwright` (239 passed; chip test verde dopo rebuild).

## Review
- Tocchi ridotti: percorso rapido = chip → importo → salva (3), contro flusso completo a 8 step.
- Chip non più scambiabili per sottoselezione del tipo: ora card in sezione dedicata sopra la scelta manuale.
- Guidato: `quickFlow` salta gli step intermedi; "Indietro" dal passo importo torna alla scelta.
- Classico: `quickMode` comprime categoria/conto/data/opzioni in riepilogo con "Modifica dettagli".
- FAB mobile: aggiunto `id` form al guidato per submit dal pulsante centrale.

# Widget metriche dinamiche (MR1 + MR2)

## Piano
- [x] Contratto `chart_config.metric_query` + config `metric_queries.php`
- [x] Motore backend: `MetricQueryService`, builder transazioni/debiti, validazione, indici DB
- [x] Formula engine: `IF`, `WHEN`, comparatori, `ABS`/`MIN`/`MAX`/`ROUND`
- [x] Runtime params: tag, categoria, valuta, debito/credito, tipo transazione
- [x] UI: `MetricQueryBuilder`, `MetricFilterRow`, `RuntimeParameterPicker`, `AdvancedFormulaPanel`
- [x] Preview/dashboard/cache compatibili con nuovi parametri
- [x] Guida in-app + scenari preset + E2E
- [x] `make test` (961), `make pint-check`, E2E formula-widgets (15/15)

## Review
- Widget legacy con sola `financial_variable` invariati; `metric_query` bypassa formula per KPI/linea/area.
- Aggregazioni default `amount_base` (EUR normalizzato); privacy allineata alla dashboard.
- Duplicate detection estesa a `metric_query` + `parameters` nel fingerprint KPI.
- E2E: preset tag, filtri runtime in anteprima, scenario IF, controlli avanzati, refetch singolo widget.

# WFI-101 — rimozione inserimento rapido transazioni

## Piano
- [x] Rimuovere quick chips/quick flow dalle pagine `Transactions/Create` e `TransactionCreateGuided`.
- [x] Rimuovere payload `quickChips` dal controller `transactions.create`.
- [x] Eliminare servizio e test dedicati agli inserimenti rapidi.
- [ ] Verifica completa: `make test`, `make pint-check`, `make playwright`.

## Review
- Inserimento rapido transazioni rimosso da form classico e guidato: resta solo flusso manuale.
- Backend semplificato: `transactions.create` non espone più `quickChips`.
- Pulizia tecnica completata: eliminati componente UI, service backend e test legacy quick chips.
- Verifica parziale verde: `make test` e `make pint-check` passati; `make playwright` bloccato da errore ambiente Node (`npm ci` con `ENOTEMPTY` nel volume `node_modules`).

# WFI-98 + WFI-99 — Conto deposito e simulazione storico

## Piano
- [x] WFI-98: aggiungere tipo conto `savings_deposit` con campo `interest_rate` lato DB/model/request.
- [x] WFI-98: aggiornare create/edit conti (classico + guidato) mostrando il tasso solo per conto deposito.
- [x] WFI-99: calcolare proiezione futura da storico transazioni household (entrate/spese medie mensili) nel controller simulazioni.
- [x] WFI-99: aggiungere nuova tab simulazione in UI con grafico e metriche.
- [x] Test: coprire nuovo payload simulazione + validazioni conto deposito.
- [ ] Verifica completa: `make test`, `make pint-check`, `make playwright` (test + pint verdi; playwright bloccato da install browser/deps node: `oopBrowserDownload.js` mancante e `tsc: not found` nel container node).
