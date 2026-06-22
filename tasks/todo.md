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
