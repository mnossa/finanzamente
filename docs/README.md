# Documentazione — Finanzamente

## Agenti Cursor (riferimento on-demand)

| File | Contenuto |
|---|---|
| [agent/README.md](agent/README.md) | Indice regole agente, Makefile, E2E, architettura |
| Regole scoped | `.cursor/rules/*.mdc` · indice breve: `.cursorrules` |

## Infrastruttura e Deploy

| File | Contenuto |
|---|---|
| [HETZNER_SETUP.md](HETZNER_SETUP.md) | Guida completa al deploy su Hetzner Cloud: provisioning, CI/CD, SSL, backup, migrazione server, monitoraggio spazio disco |
| [DEPLOY.md](DEPLOY.md) | Pipeline CI/CD GitHub Actions: come funziona build, test e release |

## Architettura e Dati

| File | Contenuto |
|---|---|
| [database-structure.md](database-structure.md) | Schema del database: tabelle, colonne e relazioni |
| [diagrams.md](diagrams.md) | Diagrammi ER delle entità principali |
| [piani.md](piani.md) | Descrizione dei piani Free e Premium e differenze |

## Marketing e Landing

| File | Contenuto |
|---|---|
| [landing-pages.md](landing-pages.md) | Struttura e copy delle landing page per target |
| [magazine-admin.md](magazine-admin.md) | Guida operativa admin magazine: articoli, categorie, env vars, ciclo di vita bozza/pubblicazione |
| [servizi-integrati.md](servizi-integrati.md) | Servizi esterni integrati (Brevo, Mollie, Telegram, ecc.) |

## Funzionalità (specifiche tecniche)

| File | Contenuto |
|---|---|
| [features/detrazioni-fiscali.md](features/detrazioni-fiscali.md) | Gestione detrazioni fiscali 730 |
| [features/recurring-transactions.md](features/recurring-transactions.md) | Sistema transazioni ricorrenti |
| [features/inter-household-transfers.md](features/inter-household-transfers.md) | Trasferimenti tra household |
| [features/debt-transaction-link.md](features/debt-transaction-link.md) | Collegamento transazioni a debiti/crediti |
| [features/profile-quiz.md](features/profile-quiz.md) | Quiz di profilazione utente |
| [features/homepage-implementation.md](features/homepage-implementation.md) | Implementazione homepage pubblica |
