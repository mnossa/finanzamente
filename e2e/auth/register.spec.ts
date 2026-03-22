import { test, expect } from '@playwright/test';

/**
 * Test E2E — Registrazione
 *
 * Copre: visualizzazione del form, campi condizionali (persona fisica / P.IVA),
 * validazione lato client e server, link alla pagina di login.
 */
test.describe('Autenticazione — Registrazione', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/register');
    });

    test('mostra il form di registrazione con tutti i campi base', async ({ page }) => {
        await expect(page).toHaveTitle(/Registrati/i);
        await expect(page.locator('#name')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#password')).toBeVisible();
        await expect(page.locator('#password_confirmation')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Registrati' })).toBeVisible();
    });

    test('mostra il campo Codice Fiscale per persona fisica (default)', async ({ page }) => {
        await expect(page.locator('#fiscal_code')).toBeVisible();
        await expect(page.locator('#vat_number')).not.toBeVisible();
    });

    test('mostra il campo Partita IVA selezionando "Partita IVA"', async ({ page }) => {
        await page.locator('#user_type').selectOption('partita_iva');
        await expect(page.locator('#vat_number')).toBeVisible();
        await expect(page.locator('#fiscal_code')).not.toBeVisible();
    });

    test('validazione: errore se le password non corrispondono', async ({ page }) => {
        await page.locator('#name').fill('Mario Rossi');
        await page.locator('#email').fill(`e2e-${Date.now()}@esempio.it`);
        await page.locator('#password').fill('password123!');
        await page.locator('#password_confirmation').fill('passwordDiversa999!');
        await page.getByRole('button', { name: 'Registrati' }).click();

        await expect(
            page.getByText(/conferma password|non corrispond/i)
        ).toBeVisible({ timeout: 8_000 });
    });

    test('validazione: errore se email già in uso', async ({ page }) => {
        const existingEmail = process.env.E2E_USER_EMAIL ?? 'e2e@finanzamente.test';

        await page.locator('#name').fill('Utente Duplicato');
        await page.locator('#email').fill(existingEmail);
        await page.locator('#password').fill('password');
        await page.locator('#password_confirmation').fill('password');
        await page.getByRole('button', { name: 'Registrati' }).click();

        await expect(
            page.getByText(/email.*già.*registrat|email.*già.*utilizz|già stato preso/i)
        ).toBeVisible({ timeout: 8_000 });
    });

    test('il link "Hai già un account?" porta a /login', async ({ page }) => {
        await page.getByRole('link', { name: /hai.*account/i }).click();
        await expect(page).toHaveURL('/login');
    });
});
