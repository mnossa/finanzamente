# Passkey / sblocco biometrico PWA

## Goal
Login con WebAuthn platform authenticator (impronta / Face ID) sulla PWA mobile, senza Capacitor. Desktop: form password resta primario; UI biometrica gated su mobile/PWA.

## Plan
- [x] Install `laravel/passkeys` + `@laravel/passkeys`
- [x] Publish migration/config; User implementa `PasskeyUser`
- [x] Custom: solo platform authenticator; redirect dashboard; post-login inviti household
- [x] UI profilo: gestione chiavi di accesso (IT copy)
- [x] UI login: CTA biometrica (standalone PWA / viewport mobile)
- [x] Feature tests (≥1) + `make test` + `make pint-check`
- [x] `make playwright` (273 passed; fix locator formula-widgets preesistente)
- [x] Commit / push / PR

## Review
### Cosa è cambiato
- Package ufficiale `laravel/passkeys` + client `@laravel/passkeys`
- Registrazione solo **platform** authenticator (`GeneratePlatformRegistrationOptions`)
- Profilo: sezione chiavi + pagina manage dietro `password.confirm`
- Login: CTA “Accedi con impronta o Face ID” se PWA/mobile + WebAuthn supportato
- Post-login passkey: redirect dashboard + pending household invites
- Fix E2E: locator `/Barre/i` → `.first()` (strict mode, 3 bottoni barre)

### Verifica
- PHPUnit: 1088 passed (incl. `PasskeyAuthenticationTest`)
- Pint: green
- Build frontend: OK
- Playwright: 273 passed, 9 skipped (dopo fix locator)
