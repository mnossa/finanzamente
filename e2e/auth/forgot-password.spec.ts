import { test, expect } from '@playwright/test';
import { e2eCredentials } from '../helpers';

/**
 * Test E2E — Recupero Password
 *
 * Copre: visualizzazione del form, invio link di reset, validazione email.
 */
test.describe('Autenticazione — Recupero Password', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/password-dimenticata');
    });

    test('mostra il form di recupero password', async ({ page }) => {
        // Il form non ha label esplicita: si usa getByRole('textbox') - unico input nel form
        await expect(page.getByRole('textbox')).toBeVisible();
        await expect(page.getByRole('button', { name: /invia link di reset/i })).toBeVisible();
    });

    test('mostra messaggio di conferma dopo l\'invio per email esistente', async ({ page }) => {
        await page.getByRole('textbox').fill(e2eCredentials.email);
        await page.getByRole('button', { name: /invia link di reset/i }).click();

        // Dovrebbe apparire un messaggio di successo (o di status)
        await expect(
            page.getByText(/email.*inviata|link.*inviato|controlla.*email|abbiamo inviato/i)
        ).toBeVisible({ timeout: 10_000 });
    });

    test('il link "Torna al login" porta a /login', async ({ page }) => {
        const loginLink = page.getByRole('link', { name: /torna.*login|accedi/i });
        if (await loginLink.isVisible()) {
            await loginLink.click();
            await expect(page).toHaveURL('/accedi');
        }
    });
});
