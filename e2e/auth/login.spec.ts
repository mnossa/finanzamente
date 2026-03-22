import { test, expect } from '@playwright/test';

/**
 * Test E2E — Accesso (Login)
 *
 * Copre: visualizzazione del form, validazione credenziali errate,
 * login con successo, link a recupero password e registrazione.
 */
test.describe('Autenticazione — Login', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
    });

    test('mostra il form di login con tutti i campi', async ({ page }) => {
        await expect(page).toHaveTitle(/Accedi/i);
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#password')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Accedi' })).toBeVisible();
    });

    test('mostra errore per credenziali non valide', async ({ page }) => {
        await page.locator('#email').fill('nonvalido@esempio.it');
        await page.locator('#password').fill('passworderrata123');
        await page.getByRole('button', { name: 'Accedi' }).click();

        // L'errore Laravel su credenziali errate
        await expect(
            page.getByText(/credenziali.*non corrispond|queste credenziali/i)
        ).toBeVisible({ timeout: 8_000 });
    });

    test('effettua il login con credenziali corrette e va in dashboard', async ({ page }) => {
        const email    = process.env.E2E_USER_EMAIL    ?? 'e2e@finanzamente.test';
        const password = process.env.E2E_USER_PASSWORD ?? 'password';

        await page.locator('#email').fill(email);
        await page.locator('#password').fill(password);
        await page.getByRole('button', { name: 'Accedi' }).click();

        await expect(page).toHaveURL('/dashboard', { timeout: 15_000 });
    });

    test('mostra il link "Password dimenticata?"', async ({ page }) => {
        await expect(page.getByRole('link', { name: /password dimenticata/i })).toBeVisible();
    });

    test('il link "Password dimenticata?" porta a /forgot-password', async ({ page }) => {
        await page.getByRole('link', { name: /password dimenticata/i }).click();
        await expect(page).toHaveURL('/forgot-password');
    });

    test('mostra link per la registrazione', async ({ page }) => {
        await expect(page.getByRole('link', { name: /registrat/i })).toBeVisible();
    });

    test('il link di registrazione porta a /register', async ({ page }) => {
        await page.getByRole('link', { name: /registrat/i }).click();
        await expect(page).toHaveURL('/register');
    });
});
