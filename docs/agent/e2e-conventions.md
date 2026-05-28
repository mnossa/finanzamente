# Playwright E2E conventions

Scoped rule: `.cursor/rules/e2e-playwright.mdc`. Quick rule summary there; full detail here.

## Directory layout

```text
e2e/
  auth.setup.ts
  .auth/user.json          # gitignored, from setup
  public/welcome.spec.ts
  auth/login.spec.ts
  auth/register.spec.ts
  auth/forgot-password.spec.ts
  dashboard/dashboard.spec.ts
  accounts/accounts.spec.ts
  transactions/transactions.spec.ts
  categories/categories.spec.ts
  budgets/budgets.spec.ts
  household/household.spec.ts
  profile/profile.spec.ts
```

New feature "Goals" → `e2e/goals/goals.spec.ts`.

## Playwright projects

- **setup** — runs `auth.setup.ts`, writes `e2e/.auth/user.json`
- **pubblico** — no session (public pages + auth flows), Chrome desktop
- **autenticato-desktop** — with session, Chrome desktop (depends on setup)
- **autenticato-mobile** — with session, Pixel 5 (depends on setup)

## E2E database

Isolated MySQL `db_e2e` (port 3307). `make e2e-seed` resets E2E data only. See root `README.md` E2E section.

## Seeder (`E2ESeeder`)

- User: `e2e@finanzamente.test` / `password`
- Household: "Casa E2E"
- Base categories and currencies

Run `make e2e-seed` before every E2E run.

## Writing / updating tests

1. Every new user-visible page → at least one load/smoke E2E test.
2. Title, button labels, field ids, nav changes → update specs.
3. Prefer `getByRole`, `getByLabel`, `getByText` over fragile CSS.
4. Unique names: `` `Categoria E2E ${Date.now()}` ``.
5. Authenticated tests: `storageState` from setup — no manual login (except `e2e/auth/`, `e2e/public/`).
6. Specs must not depend on execution order or data from other specs.
7. Assertions use **Italian** UI strings.
8. Extra seed data → `E2ESeeder` + update affected tests.
9. CI: `.github/workflows/playwright.yml`.

## E2E app overrides (`app_e2e`)

- `BREVO_ENABLED=false`
- `FEATURE_GUIDED_CREATE_FORMS=false` (classic forms for Playwright)

After E2E env changes: `make e2e-seed` to regenerate `config_e2e.php`.
