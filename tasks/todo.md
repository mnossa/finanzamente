# Fix PWA biometric / passkey registration

## Goal
Far funzionare registrazione impronta da PWA (NotReadableError Android CM).

## Plan
- [x] Soften UV a `preferred`; mode `?compatibility=1` (no platform preference)
- [x] Client `registerDevicePasskey` con retry compatibility
- [x] UI PWA: CTA «Apri in browser» + copy Android (Google Password Manager)
- [x] Workbox NetworkOnly esplicito su `/user/passkeys` e `/passkeys/`
- [x] Test PHP aggiornati + make test/pint/playwright

## Review
### Cosa cambiato
- Backend: UV preferred; `?compatibility=1` senza attachment platform
- `registerPasskey.ts`: create + store custom, retry su NotReadableError
- Form: CTA «Apri in browser» in standalone; hint Google Password Manager
- SW: NetworkOnly per API passkey
- Errori IT più actionable

### Verifica
- PHPUnit: green (incluso PasskeyAuthenticationTest)
- Pint: green
- Playwright: green
- Build: green (nuovo SW)

### Come riprovare sul telefono
1. Deploy / aggiorna PWA → banner «Ricarica» o reinstall
2. Profilo → Configura sblocco biometrico
3. Se fallisce dall’app: «Apri in browser» e registra da Chrome
4. Android: gestore password preferito = Google Password Manager
