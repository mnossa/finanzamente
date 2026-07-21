# WFI-105 — Review IA navigazione

## Piano (ordine: Fase 1 → 2 → 3)

### Fase 1 — WFI-110 IA core
- [x] `PatrimonioHubNav` + hub su patrimonio, accounts, investments
- [x] Sidebar: sezione Patrimonio; rimuovere Investimenti top-level
- [x] `mobileBottomNav`: destination `patrimonio`, default 4 slot
- [x] Icona Organizzazione in app bar mobile
- [x] `CashflowHubNav`: solo Transazioni · Conti
- [x] Redirect soft `transfers.index` → `transactions.index`
- [x] Filtrare `requiresPro` da sidebar
- [x] Test Feature nav + E2E smoke

### Fase 2 — WFI-111 Dashboard Essenziale
- [x] `DashboardLayout::essentialConfigForUser()`
- [x] Applicare solo nuovi utenti senza layout salvato
- [x] Test DashboardLayout

### Fase 3 — WFI-112 Slide-over + Pro
- [x] `TransactionSlideOver` (md+)
- [x] Value moments Pro (conto investimento, patrimonio preview)
- [x] E2E slide-over

## Verify
- [x] `make test`
- [x] `make pint-check`
- [x] `make playwright`

## Review
- Fase 1–3 WFI-105 complete.
- Slide-over: `transactions.show` JSON + `TransactionSlideOver` md+; mobile portrait → pagina Show.
- Modifica in slide-over → pagina edit (create resta AS-IS).