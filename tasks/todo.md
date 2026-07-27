# Fix passkey / impronta

## Goal
1. Registrazione trasparente: niente campo "Nome della chiave"
2. Ridurre/gestire errore Credential Manager in registrazione biometrica

## Plan
- [x] UI: rimuovere input nome; auto-nome dispositivo; CTA unica
- [x] Map errori WebAuthn/Credential Manager → italiano actionable
- [x] Hardening options: RP name app, hints client-device, displayName non vuoto
- [x] Config origins/RP ID più robusta; Permissions-Policy publickey; .well-known
- [x] Test feature + make test + pint-check + playwright

## Review
### Cosa è cambiato
- Form manage: niente nome chiave; CTA “Registra sblocco biometrico”; nome auto (`Android`/`iPhone`/…)
- `GeneratePlatformRegistrationOptions`: hints `client-device`, RP name = APP_NAME, displayName fallback, excludeCredentials resiliente
- Errori IT via `passkeyErrors.ts` (Credential Manager / NotReadableError)
- Origins www/apex + env overrides; Permissions-Policy publickey; `/.well-known/` in prod nginx

### Verifica
- PHPUnit: 1090 passed (PasskeyAuthenticationTest 9/9)
- Pint: green (708 files)
- Playwright: 275 passed, 9 skipped
