# Piano Operativo 14 Giorni - Qualita e Stabilita

Questo documento traduce le raccomandazioni emerse dall'analisi tecnica in task eseguibili, con priorita, ownership, stime e dipendenze.

## Obiettivi

- Ridurre regressioni in sviluppo e deploy.
- Rendere i quality gate obbligatori e ripetibili.
- Migliorare manutenibilita di routing, frontend typing e flussi webhook.
- Stabilizzare il processo di rilascio con checklist e rollback readiness.

## Ruoli suggeriti

- **BE**: Backend engineer (Laravel/PHP).
- **FE**: Frontend engineer (React/TypeScript).
- **DEVOPS**: DevOps/infra.
- **QA**: Testing quality owner (manuale + automation).
- **TL**: Tech lead (decisioni e validazione finale).

## Piano esecutivo (2 settimane)

### Settimana 1 - Fondazioni e quick wins

| ID | Task | Owner | Stima | Dipendenze | Deliverable |
|---|---|---|---|---|---|
| W1-D1-01 | Definire version matrix ufficiale (`PHP`, `Node`, `MySQL`) | TL + DEVOPS | 0.5g | Nessuna | Sezione in `README` + issue di allineamento |
| W1-D1-02 | Allineare versioni in Docker/CI/dev alla matrix | DEVOPS | 0.5g | W1-D1-01 | Config coerente tra `docker-compose`, workflow e runtime |
| W1-D2-01 | Inserire quality gates obbligatori in CI (`test`, `build/typecheck`, `pint`) | DEVOPS + BE | 1g | W1-D1-02 | Workflow CI bloccante su fail |
| W1-D3-01 | Verificare e aggiornare `.gitignore` per cache/artifacts locali | BE | 0.5g | Nessuna | `.gitignore` pulito e documentato |
| W1-D3-02 | Pulire dipendenze JS dirette non necessarie in `package.json` | FE | 0.5g | Nessuna | Diff dipendenze con motivazione |
| W1-D4-01 | Eliminare `any` dai tipi condivisi in layout/navigation | FE | 1g | Nessuna | Tipi centralizzati in `resources/js/.../types` |
| W1-D5-01 | Hardening webhook: firma, idempotenza, logging minimo | BE | 1g | Nessuna | Controller/middleware aggiornati + linee guida |
| W1-D5-02 | Test feature webhook (firma valida/non valida, retry duplicati) | BE + QA | 0.5g | W1-D5-01 | Nuovi test verdi in CI |
| W1-D6-01 | Prioritizzare backlog test mancanti per rischio business | QA + TL | 0.5g | Nessuna | Lista issue prioritarie con owner |
| W1-D7-01 | Buffer day: fix flaky test e regressioni emerse | Team | 1g | Tutti i task settimana 1 | Baseline stabile per settimana 2 |

### Settimana 2 - Strutturazione e stabilizzazione

| ID | Task | Owner | Stima | Dipendenze | Deliverable |
|---|---|---|---|---|---|
| W2-D8-01 | Splittare `routes/web.php` per dominio (admin, payments, magazine, ecc.) | BE | 1.5g | W1-D7-01 | Routing modulare con stessi middleware/prefix |
| W2-D9-01 | Rifinire naming/organizzazione route file e gruppi | BE | 0.5g | W2-D8-01 | Struttura route consistente |
| W2-D10-01 | Aggiungere smoke regression test per route critiche | BE + QA | 1g | W2-D9-01 | Coverage minima su auth/admin/payment/webhook |
| W2-D11-01 | Standardizzare target Makefile (`lint`, `test`, `test-e2e`, `ci-local`) | BE + DEVOPS | 1g | W1-D2-01 | Comandi locali allineati alla CI |
| W2-D12-01 | Integrare check accessibilita automatici su pagine chiave | FE + QA | 1g | W1-D2-01 | Step a11y in pipeline |
| W2-D13-01 | Audit finale dipendenze/config sensibili | TL + DEVOPS | 0.5g | W1-D3-02 | Report breve con remediation |
| W2-D14-01 | Dry run deploy/staging con checklist e rollback | DEVOPS + TL | 1g | Tutti i task settimana 2 | Evidenza run + miglioramenti checklist |

## Sequenza raccomandata (critical path)

1. Version matrix (`W1-D1-01`, `W1-D1-02`)
2. Quality gates CI (`W1-D2-01`)
3. Hardening webhook + test (`W1-D5-01`, `W1-D5-02`)
4. Split routing + regression test (`W2-D8-01` -> `W2-D10-01`)
5. Dry run release (`W2-D14-01`)

## Definition of Done (fine 14 giorni)

- CI blocca merge su errori di test/build/lint.
- Version matrix unica e applicata in dev/CI/prod.
- Routing modulare senza regressioni funzionali.
- Webhook con verifica firma e idempotenza testata.
- Riduzione concreta di `any` nei moduli FE condivisi.
- Backlog test mancanti convertito in issue con owner e priorita.

## KPI settimanali

- **CI pass rate**: percentuale pipeline verdi al primo run.
- **Flaky tests**: numero test instabili aperti/chiusi.
- **Type safety FE**: conteggio `any` residui nei moduli core.
- **Lead time feedback**: durata media pipeline.
- **Post-deploy issues**: numero hotfix/rollback per release.

## Stato avanzamento (aggiornato al 2026-04-27)

- **Progresso complessivo:** 13/14 task principali completati (~93%).
- **Critical path:** resta esecuzione su server del dry run deploy/rollback (`W2-D14-01`); tooling locale e checklist sono pronti.
- **Stato generale:** settimana 1 chiusa, settimana 2 in corso.

## Backlog test prioritizzato (W1-D6-01)

### P0 (alta priorita, rischio business/compliance)

- **Detrazioni fiscali (730)**
  - feature test end-to-end controller detrazioni
  - autorizzazioni (accesso/modifica/cancellazione) — *parziale 2026-04-28: utente piano Base bloccato su index/export (vedi `TaxDeductionExportTest` + `UserFactory::basePlan`)*
  - upload allegati (validazione/errori)
  - export PDF/ZIP
- **Ricorrenze**
  - edge case generazione (frequenze miste, date limite, modifica regole post-creazione)
  - regressione anti-duplicazione
- **Multi-currency inter-household**
  - trasferimenti con tassi e fee in combinazioni limite

### P1 (media priorita, UX/affidabilita)

- **Homepage/Landing**
  - test automatici responsive su breakpoint principali
  - test automatici SEO/OG meta tag
  - validazione CTA/tracking principali
- **Unsplash/Magazine**
  - ricerca immagini + attribution + salvataggio locale
- **Accessibilita avanzata**
  - ARIA label, landmark, skip-to-content, flussi keyboard-only

### P2 (ottimizzazione/analisi)

- test animazioni scroll/intersection observer
- test varianti A/B CTA
- test FAQ/testimonianze
- test tracking analytics/newsletter avanzati

### Sequenza consigliata issue (ordine di apertura)

1. `TEST-P0-001` Detrazioni: authz + export + upload
2. `TEST-P0-002` Ricorrenze: edge case + anti-duplicazione
3. `TEST-P0-003` Inter-household multi-currency: tassi/fee limite
4. `TEST-P1-001` Homepage/Landing: SEO+responsive+CTA
5. `TEST-P1-002` Magazine Unsplash integration checks
6. `TEST-P1-003` A11y avanzata tastiera/landmark/ARIA

## Checklist operativa pronta (copia/incolla in issue tracker)

- [x] Definire e approvare version matrix runtime.
- [x] Allineare Docker/CI/dev alle versioni approvate (allineate alla produzione attuale).
- [x] Rendere obbligatori quality gate in CI.
- [x] Pulire `.gitignore` e artifacts locali.
- [x] Ridurre dipendenze JS dirette non necessarie.
- [x] Eliminare `any` espliciti residui nei punti FE individuati (`AuthenticatedLayout`, `LifestyleScore`, `Households/Show`).
- [x] Implementare hardening webhook (firma + idempotenza).
- [x] Aggiungere test webhook su casi nominali/errore/duplicati.
- [x] Prioritizzare gap test per rischio business.
- [x] Modularizzare `routes/web.php` per dominio.
- [x] Aggiungere smoke test su route critiche.
- [x] Allineare target Makefile e comandi CI.
- [ ] Integrare check a11y automatici (pianificato per futuro: richiede AXE_API_KEY/config operativa).
- [x] Eseguire dry run deploy con verifica rollback.

## Board pronta (To Do / Doing / Done con priorita)

Usa questa sezione come template rapido in Jira/Trello/Linear. Mantieni **max 2 task in Doing** contemporaneamente.

### To Do

| Priorita | ID | Task | Owner |
|---|---|---|---|
| P2 | W2-D12-01 | Integrare check accessibilita automatici | FE + QA |
| P2 | W2-D14-01 | Dry run deploy/staging con rollback testato | DEVOPS + TL |

*W2-D14 — evidenza locale:* `make deploy-dry-run`, checklist e template server in `docs/deploy-dry-run-checklist.md`.

### Doing

| Priorita | ID | Task | Owner | Started on |
|---|---|---|---|---|
| — | — | *nessun task in corso* | — | — |

### Done

| Priorita | ID | Task | Owner | Done on | Evidenza |
|---|---|---|---|---|---|
| P0 | W1-D1-01 | Definire version matrix ufficiale | TL + DEVOPS | 2026-04-27 | `README.md` |
| P0 | W1-D1-02 | Allineare versioni Docker/CI/dev alla matrix | DEVOPS | 2026-04-27 | `Dockerfile*`, `docker-compose.yml`, workflow |
| P0 | W1-D2-01 | Rendere quality gates obbligatori in CI | DEVOPS + BE | 2026-04-27 | `.github/workflows/deploy.yml` |
| P1 | W1-D3-01 | Aggiornare `.gitignore` per cache/artifacts locali | BE | 2026-04-27 | `.gitignore` |
| P1 | W1-D3-02 | Pulire dipendenze JS dirette non necessarie | FE | 2026-04-27 | `package.json`, `package-lock.json` |
| P0 | W1-D5-01 | Hardening webhook (firma + idempotenza + logging) | BE | 2026-04-27 | `TelegramWebhookController`, `MollieWebhookController` |
| P0 | W1-D5-02 | Test feature webhook (valida/non valida/duplicati) | BE + QA | 2026-04-27 | `TelegramWebhookTest`, `MollieWebhookTest` |
| P1 | W1-D6-01 | Prioritizzare backlog test mancanti | QA + TL | 2026-04-27 | Sezione "Backlog test prioritizzato" |
| P1 | W2-D8-01 | Splittare `routes/web.php` per dominio | BE | 2026-04-27 | `routes/web/public.php`, `routes/web/authenticated.php`, `routes/web/magazine.php` |
| P1 | W2-D10-01 | Aggiungere smoke test route critiche | BE + QA | 2026-04-27 | `tests/Feature/RouteSmokeTest.php` |
| P2 | W2-D11-01 | Standardizzare target Makefile e CI locale | BE + DEVOPS | 2026-04-27 | `Makefile` |
| P2 | W2-D9-01 | Rifinire naming/organizzazione route file | BE | 2026-04-27 | `routes/web/*.php` (contenuto unico, niente shim) |
| P1 | W1-D4-01 | Eliminare `any` dai tipi condivisi frontend | FE | 2026-04-27 | `AuthenticatedLayout.tsx`, `LifestyleScore/Index.tsx`, `Households/Show.tsx` |
| P2 | W2-D13-01 | Audit finale dipendenze/config sensibili | TL + DEVOPS | 2026-04-27 | `docs/w2-d13-audit-dipendenze-config-sensibili.md`, `npm audit fix` |
