# Feature Matrix — Finanzamente

Data ultimo aggiornamento: 2026-04-28

## Criteri stato

- `Attiva`: disponibile in produzione con backend + frontend + almeno un test automatico.
- `Parziale`: disponibile ma con copertura test o integrazione incompleta.
- `Roadmap`: non ancora implementata o esplicitamente pianificata per release future.

## Matrice sintetica

- `Autenticazione` — **Attiva**
  - Backend: registrazione/login/reset/verifica email.
  - Frontend: pagine auth pubbliche.
  - Test Unit/Feature: presenti.
  - Test E2E: presenti (`e2e/auth/*`, `e2e/public/*`).

- `Household` — **Attiva**
  - Backend: selezione, creazione, inviti, gestione membri.
  - Frontend: flussi household autenticati.
  - Test Unit/Feature: presenti.
  - Test E2E: presenti (`e2e/household/household.spec.ts`).

- `Conti e Transazioni` — **Attiva**
  - Backend: CRUD conti/transazioni, import, filtri, bulk actions.
  - Frontend: pagine `accounts` e `transactions`.
  - Test Unit/Feature: presenti.
  - Test E2E: presenti (`e2e/accounts/*`, `e2e/transactions/*`).

- `Budget, Obiettivi, Debiti/Crediti` — **Attiva**
  - Backend: CRUD + logiche di avanzamento.
  - Frontend: pagine dedicate.
  - Test Unit/Feature: presenti.
  - Test E2E: presenti per budget e obiettivi; debiti/crediti da rafforzare.

- `Investimenti` — **Attiva**
  - Backend: asset, posizioni, analisi, import.
  - Frontend: `investments`, `asset-allocation`, `investment-assets`, `investment-analyses`.
  - Test Unit/Feature: presenti.
  - Test E2E: presenti (`e2e/investments/investments.spec.ts`).

- `Subscription e Billing` — **Parziale**
  - Backend: checkout, ritorno, cancellazione, update billing, webhook Mollie.
  - Frontend: pagina profilo abbonamento.
  - Test Unit/Feature: presenti (`SubscriptionTest`, `MollieWebhookTest`).
  - Test E2E: smoke presente; manca scenario end-to-end checkout con callback mock.

- `Inter-household transfers` — **Attiva**
  - Backend: creazione, lista, dettaglio, validazioni household.
  - Frontend: flussi dedicati.
  - Test Unit/Feature: presenti.
  - Test E2E: presenti (`e2e/inter-household/inter-household.spec.ts`).

- `Prelaunch / Waitlist` — **Attiva**
  - Backend: middleware prelaunch, endpoint waitlist, integrazione Brevo.
  - Frontend: CTA waitlist e vincoli registrazione.
  - Test Unit/Feature: presenti (`WaitlistTest`).
  - Test E2E: presenti (modes + public flows).

- `Consensi GDPR granulari` — **Attiva**
  - Backend: modello consensi, export JSON, preferenze profilo.
  - Frontend: UI consensi in profilo.
  - Test Unit/Feature: presenti.
  - Test E2E: presenti (`e2e/profile/profile.spec.ts`).

- `MFA TOTP (autenticazione a due fattori)` — **Attiva**
  - Backend: TOTP RFC 6238, recovery codes, challenge al login.
  - Frontend: wizard profilo + pagina verifica login.
  - Test Unit/Feature: `TwoFactorAuthenticationTest`.
  - Test E2E: `e2e/auth/two-factor.spec.ts`.

- `Export portabilità dati profilo` — **Attiva**
  - Backend: `ProfileDataExportService`, rotta `/profilo/export-dati` con conferma password.
  - Frontend: card Condivisione e dati nel profilo.
  - Test Feature: `ProfileDataExportTest`.
