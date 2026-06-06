# Architecture & advanced specs (on-demand)

## Layer split
- **Public**: Blade + Tailwind, SEO, SSR.
- **Authenticated**: Inertia + React + TypeScript — no Blade.
- **Backend**: Laravel services, policies, web routes, session auth.
- Document and test both frontend layers.

## Extensibility (design for)
Reporting, notifications, external integrations, multi-household, multi-currency, budgeting, debts/credits, goals, tagging, multi-attachments — without invasive schema changes when possible.

## Advanced (implement when requested)
| Topic | Notes |
|-------|--------|
| WCAG 2.1 | Contrast, keyboard, screen readers |
| Rate limiting | All sensitive web routes; extend to future APIs |
| PWA | `vite-plugin-pwa` → `public/build/sw.js` + `manifest.webmanifest` after `make build`. Icons: `make pwa-icons` from `public/images/finanzamente-logo.webp`. Nginx: `Service-Worker-Allowed: /` on `/build/sw.js`. Re-install app on device after icon change. |
| Logging/monitoring | Centralized, privacy-friendly |
| Backup/DR | Automated backup + restore procedures |
| Analytics | Privacy-friendly usage metrics |
| DevOps | Deploy, rollback, env docs → `docs/DEPLOY.md` |

## UX product (finanza)
- Operazioni tecniche (sync ledger, realign PAC, migrate dati) **mai** esposte in UI con comandi shell.
- Testi utente in italiano plain: evitare “ledger”, “collegati/non collegati”, “sync”.

## Deploy / backfill
- **Ogni deploy** (entrypoint): solo operazioni idempotenti (`migrate`, `optimize`, `sitemap:generate`, …).
- **One-shot** dopo cambio logica dati: **migration** Laravel (eseguita una volta per ambiente al primo `migrate`), non artisan ripetuto in `entrypoint.sh`.
- Nuovi investimenti con conto: `InvestmentObserver` (nessun job deploy necessario).

## Code quality checklist
- ESLint/Prettier (JS), Pint (PHP)
- DRY/KISS, centralized errors
- PR + review for relevant changes
- Update this doc or `.cursor/rules/` when architecture changes materially
