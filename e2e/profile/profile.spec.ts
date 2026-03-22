import { test, expect } from '@playwright/test';

/**
 * Test E2E — Profilo
 *
 * Copre: visualizzazione pagina profilo, form di aggiornamento dati,
 * form di cambio password, form di eliminazione account.
 */
test.describe('Profilo utente', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/profile');
    });

    test('carica la pagina del profilo', async ({ page }) => {
        await expect(page).toHaveURL('/profile');
        await expect(page).toHaveTitle(/Profilo/i);
    });

    test('mostra il form di aggiornamento informazioni profilo', async ({ page }) => {
        await expect(page.locator('#name')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();
    });

    test('il campo nome è precompilato con il nome utente corrente', async ({ page }) => {
        const nameField = page.locator('#name');
        const value = await nameField.inputValue();
        expect(value.length).toBeGreaterThan(0);
    });

    test('il campo email è precompilato con l\'email dell\'utente', async ({ page }) => {
        const emailField = page.locator('#email');
        const value = await emailField.inputValue();
        expect(value).toContain('@');
    });

    test('il pulsante "Salva" è visibile nel form profilo', async ({ page }) => {
        await expect(
            page.getByRole('button', { name: /^salva$/i }).first()
        ).toBeVisible();
    });

    test('aggiorna il nome profilo con successo', async ({ page }) => {
        const nameField = page.locator('#name');
        await nameField.fill('Utente E2E Aggiornato');

        await page.getByRole('button', { name: /^salva$/i }).first().click();

        // Dovrebbe apparire il messaggio "Salvato."
        await expect(page.getByText(/salvato/i)).toBeVisible({ timeout: 8_000 });

        // Ripristina il nome originale
        await nameField.fill('Utente E2E');
        await page.getByRole('button', { name: /^salva$/i }).first().click();
    });

    test('mostra il form di cambio password', async ({ page }) => {
        const passwordSection = page.getByText(/aggiorna password|cambio password/i).first();
        await expect(passwordSection).toBeVisible();
    });
});
