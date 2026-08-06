import { test, expect } from '@playwright/test';
import { e2eCredentials } from '../helpers';

/**
 * Test E2E — Accesso (Login)
 *
 * Testa il comportamento del flusso di accesso.
 * I selettori usano attributi strutturali (type, name) che non dipendono
 * da testo UI (label, placeholder, copy) che può cambiare liberamente.
 */
test.describe('Autenticazione — Login', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/accedi');
    });

    test('il form di login accetta email e password', async ({ page }) => {
        await expect(page).toHaveTitle(/Accedi/i);
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('input[type="password"]')).toBeVisible();
        await expect(page.locator('[type="submit"]')).toBeVisible();
    });

    test('credenziali non valide mostrano un errore', async ({ page }) => {
        await page.locator('input[type="email"]').fill('nonvalido@esempio.it');
        await page.locator('input[type="password"]').fill('passworderrata123');
        await page.locator('[type="submit"]').click();

        // L'errore Laravel compare come testo nel DOM — la sua posizione è stabile
        await expect(page.locator('.text-red-600, [role="alert"], .alert-error, form .text-sm')
            .filter({ hasText: /credenziali|errat|incorrect|non.*corrett/i })
        ).toBeVisible({ timeout: 8_000 });
    });

    test('login con credenziali corrette porta in dashboard', async ({ page }) => {
        const { email, password } = e2eCredentials;

        await page.locator('input[type="email"]').fill(email);
        await page.locator('input[type="password"]').fill(password);
        await page.locator('[type="submit"]').click();

        await expect(page).toHaveURL('/dashboard', { timeout: 15_000 });
    });

    test('esiste un link per il recupero password', async ({ page }) => {
        // Testa la presenza del link tramite href, non il testo visualizzato
        await expect(page.locator('a[href*="password-dimenticata"], a[href*="forgot-password"]')).toBeVisible();
    });

    test('il link recupero password porta alla pagina corretta', async ({ page }) => {
        await page.locator('a[href*="password-dimenticata"], a[href*="forgot-password"]').click();
        await expect(page).toHaveURL('/password-dimenticata');
    });

    test('esiste un link per la registrazione', async ({ page }) => {
        await expect(page.locator('a[href*="registrati"], a[href*="register"]')).toBeVisible();
    });

    test('il link registrazione porta alla pagina corretta', async ({ page }) => {
        await page.locator('a[href*="registrati"], a[href*="register"]').first().click();
        await expect(page).toHaveURL('/registrati');
    });
});
