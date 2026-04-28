# Contributor Guide

Data ultimo aggiornamento: 2026-04-28

## Pre-push obbligatorio

Prima di ogni push esegui sempre:

```bash
make test-ci
```

Questo comando replica i gate CI backend:

- verifica stile PHP (`pint --test`)
- esecuzione test Laravel

## Smoke E2E consigliato (feature critiche)

Per modifiche che toccano UI o flussi utente principali, esegui anche:

```bash
make playwright
```

Se il cambiamento riguarda solo un modulo specifico, puoi lanciare il relativo spec Playwright prima della suite completa (es. `e2e/transactions/transactions.spec.ts`), ma il merge deve avvenire con suite verde.
