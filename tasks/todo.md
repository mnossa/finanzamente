# Fix passkey / impronta

## Goal
1. Registrazione trasparente: niente campo "Nome della chiave"
2. Ridurre/gestire errore Credential Manager in registrazione biometrica

## Plan
- [ ] UI: rimuovere input nome; auto-nome dispositivo; CTA unica
- [ ] Map errori WebAuthn/Credential Manager → italiano actionable
- [ ] Hardening options: RP name app, hints client-device, displayName non vuoto
- [ ] Config origins/RP ID più robusta; Permissions-Policy publickey; .well-known
- [ ] Test feature + make test + pint-check (+ playwright se JS navigabile)

## Review
(pending)
