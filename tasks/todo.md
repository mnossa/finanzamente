# WFI-117 — Tipo Obbligazione (BTP) + ordine tipi asset UX

## Goal
BTP/obbligazioni = tipo dedicato; select tipologica per retail IT (crypto in basso).

## Plan
- [x] `bond` in TYPES/TYPE_ICONS + riordino
- [x] Validation + enum MySQL migration
- [x] AssetClassificationService bond→bonds
- [x] Optgroup/index order
- [x] Unit tests
- [x] make test + pint + build
- [ ] make playwright
- [ ] Jira Completato (comment/transition: approval se serve)

## Review
### Cosa
- Nuovo tipo `bond` = **Obbligazione** (BTP, BOT, corporate, …)
- Ordine: ETF → Azione → Obbligazione → Assicurazione → Indice → Materia Prima → Criptovaluta → Altro
- Allocazione automatica `bonds`; icona 🏛️

### Verifica
- PHPUnit: 1106 passed
- Pint: pass
- Build: OK
- Migrate enum MySQL: OK
