# WFI-106 / WFI-107 / WFI-108

## Plan
- [x] WFI-106: `tags.show` + stats mese + Tags/Show + Index link + Feature test
- [x] WFI-107: collapse Prossimi movimenti + localStorage + E2E smoke
- [x] WFI-108: analisi budget+tag → commento Jira (no code)

## Verify
- [x] `make test` (1004 passed)
- [x] `make pint-check`
- [x] Playwright transazioni (Prossimi movimenti + split payment) green dopo seed

## Review
- Tag show: mese Y-m, KPI + byCategory + link lista filtrata
- Upcoming: collapsed default, aria-expanded, storage keyed by user id
- WFI-108: raccomandazione Opzione 1 (categoria AS-IS); ownership tag vs HH apre v2
- E2E: select non-buoni per split; seed TX futura per upcoming
- Jira: commento WFI-108 ok; status Completato non auto-transition (approvare se serve)
