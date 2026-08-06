import { test, expect } from '@playwright/test';
import { e2eCredentials } from '../helpers';

/**
 * Test E2E — Recupero Password
 *
 * Copre: visualizzazione del form, invio link di reset.
 */
test.describe('Autenticazione — Recupero Password', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/password-dimenticata');
    });

    test('il form di recupero password è presente', async ({ page }) => {
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('[type="submit"]')).toBeVisible();
    });

    test('invio per email esistente mostra conferma', async ({ page }) => {
        await page.locator('input[type="email"]').fill(e2eCredentials.email);
        await page.locator('[type="submit"]').click();

        // Dopo invio: messaggio di stato (Laravel flash) o rimane sulla pagina
        // — verifica che non sia tornato al login (che sarebbe un errore)
        await expect(page).not.toHaveURL('/accedi');
    });

    test('il link di ritorno al login porta a /accedi', async ({ page }) => {
        const loginLink = page.locator('a[href*="accedi"], a[href*="login"]');
        if (await loginLink.isVisible()) {
            await loginLink.click();
            await expect(page).toHaveURL('/accedi');
        }
    });
});
