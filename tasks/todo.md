# Dashboard widgets + privacy + homepage

## Goal
1. Far aggiungere Lifestyle Inflation Score (e altri built-in) dal catalogo in edit mode
2. Oscurare importi in tutti i widget dashboard quando hide balances attivo
3. Homepage: allineare copy a feature reali (niente “730” come prodotto)

## Plan
- [x] `addWidget` in `useDashboardLayout` + modal “Aggiungi widget” da `WIDGET_REGISTRY`
- [x] `moneyTabular` / CSS sensitive su Lifestyle, ExpenseDistribution, Treemap, PAC, FormulaTable/Chart tooltips
- [x] Homepage: “Detrazioni e 730” → tracker spese detraibili; aggiorna `HomepageContentTest`
- [x] Soft-fix landing `lavoratori` (lezione 730)
- [x] E2E: aggiungi widget in personalizzazione
- [x] `make test` → `make pint-check` → boards E2E (full playwright: 279 pass + 2 fail fixati)

## Review
### Cosa
- Edit dashboard: bottone **Aggiungi widget** → modal da `WIDGET_REGISTRY` (Lifestyle, PAC, … se assenti dal layout)
- Privacy saldi: `moneyTabular` su Lifestyle / distribuzione / treemap / PAC tooltip / formula table+chart; CSS blur assi Y Recharts
- Home: “Tracker spese detraibili”; landing lavoratori SEO/copy senza overclaim 730; mock “Totale spese marcate”

### Verifica
- PHPUnit: 1029 passed
- Pint: PASS
- Playwright boards.spec: 5 passed (desktop+mobile+setup)
- Full suite precedente: 279 passed, 2 fail solo boards (fixati)

### Come usare Lifestyle
Le mie dashboard → Personalizza → **Aggiungi widget** → Lifestyle Inflation Score → Salva layout  
(Home Essenziale non lo include di default; E2E seed usa Completa e lo ha già.)
