# Passkey / sblocco biometrico PWA

## Goal
Login con WebAuthn platform authenticator (impronta / Face ID) sulla PWA mobile, senza Capacitor. Desktop: form password resta primario; UI biometrica gated su mobile/PWA.

## Plan
- [ ] Install `laravel/passkeys` + `@laravel/passkeys`
- [ ] Publish migration/config; User implementa `PasskeyUser`
- [ ] Custom: solo platform authenticator; redirect dashboard; post-login inviti household
- [ ] UI profilo: gestione chiavi di accesso (IT copy)
- [ ] UI login: CTA biometrica (standalone PWA / viewport mobile)
- [ ] Feature tests (≥1) + `make test` + `make pint-check`
- [ ] Playwright: no regressione login (omit solo se nessun markup navigabile — qui c’è, quindi run)
- [ ] Commit / push / PR

## Review
(pending)
