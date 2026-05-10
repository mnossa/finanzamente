import { test, expect } from '@playwright/test';
import { e2eCredentials } from '../helpers';

/**
 * Test E2E — Registrazione
 *
 * Copre: visualizzazione del form, campi condizionali (persona fisica / P.IVA),
 * validazione lato client e server, link alla pagina di login.
 */
test.describe('Autenticazione — Registrazione', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/registrati');
    });

    test('mostra il form di registrazione con tutti i campi base', async ({ page }) => {
        await expect(page).toHaveTitle(/Registrati/i);
        await expect(page.getByLabel(/nome/i)).toBeVisible();
        await expect(page.getByLabel(/^email/i)).toBeVisible();
        await expect(page.getByLabel(/^password \*/i)).toBeVisible();
        await expect(page.getByLabel(/conferma password/i)).toBeVisible();
        await expect(page.getByRole('button', { name: 'Registrati' })).toBeVisible();
    });

    test('mostra il campo Codice Fiscale per persona fisica (default)', async ({ page }) => {
        await expect(page.getByLabel(/codice fiscale/i)).toBeVisible();
        await expect(page.getByLabel(/partita iva/i)).not.toBeVisible();
    });

    test('mostra il campo Partita IVA selezionando "Partita IVA"', async ({ page }) => {
        await page.getByLabel(/tipo utente/i).selectOption('partita_iva');
        await expect(page.getByLabel(/partita iva/i)).toBeVisible();
        await expect(page.getByLabel(/codice fiscale/i)).not.toBeVisible();
    });

    test('validazione: errore se le password non corrispondono', async ({ page }) => {
        await page.getByLabel(/nome/i).fill('Mario Rossi');
        await page.getByLabel(/^email/i).fill(`e2e-${Date.now()}@esempio.it`);
        await page.getByLabel(/^password \*/i).fill('password123!');
        await page.getByLabel(/conferma password/i).fill('passwordDiversa999!');
        await page.getByRole('button', { name: 'Registrati' }).click();

        await expect(
            page.getByText(/conferma password|non corrispond/i)
        ).toBeVisible({ timeout: 8_000 });
    });

    test('validazione: errore se email già in uso', async ({ page }) => {
        const existingEmail = e2eCredentials.email;

        await page.getByLabel(/nome/i).fill('Utente Duplicato');
        await page.getByLabel(/^email/i).fill(existingEmail);
        await page.getByLabel(/^password \*/i).fill('password');
        await page.getByLabel(/conferma password/i).fill('password');
        await page.getByRole('button', { name: 'Registrati' }).click();

        await expect(
            page.getByText(/email.*già.*registrat|email.*già.*utilizz|già stato preso/i)
        ).toBeVisible({ timeout: 8_000 });
    });

    test('il link "Hai già un account?" porta a /login', async ({ page }) => {
        await page.getByRole('link', { name: /hai.*account/i }).click();
        await expect(page).toHaveURL('/accedi');
    });
});
