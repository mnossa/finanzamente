import { test, expect } from '@playwright/test';
import { e2eCredentials } from '../helpers';

/**
 * Test E2E — Registrazione
 *
 * Testa il comportamento del flusso di registrazione.
 * Selettori: name/type degli input (legati al form HTTP e alla validazione backend),
 * non al testo UI che può cambiare liberamente.
 */
test.describe('Autenticazione — Registrazione', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/registrati');
    });

    test('il form di registrazione è presente e accetta l\'input', async ({ page }) => {
        await expect(page).toHaveTitle(/Registrati/i);
        await expect(page.locator('input[name="name"]')).toBeVisible();
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
        await expect(page.locator('input[name="password_confirmation"]')).toBeVisible();
        await expect(page.locator('[type="submit"]')).toBeVisible();
    });

    test('il campo fiscal_code è visibile per il tipo persona (default)', async ({ page }) => {
        await expect(page.locator('input[name="fiscal_code"]')).toBeVisible();
        await expect(page.locator('input[name="vat_number"]')).not.toBeVisible();
    });

    test('selezionando partita_iva cambia il campo fiscale', async ({ page }) => {
        await page.locator('select[name="user_type"]').selectOption('partita_iva');
        await expect(page.locator('input[name="vat_number"]')).toBeVisible();
        await expect(page.locator('input[name="fiscal_code"]')).not.toBeVisible();
    });

    test('password e conferma non coincidenti producono un errore', async ({ page }) => {
        await page.locator('input[name="name"]').fill('Mario Rossi');
        await page.locator('input[name="email"]').fill(`e2e-${Date.now()}@esempio.it`);
        await page.locator('input[name="password"]').fill('password123!');
        await page.locator('input[name="password_confirmation"]').fill('passwordDiversa999!');
        await page.locator('[type="submit"]').click();

        // Dopo submit fallito l'URL rimane su /registrati (nessun redirect a dashboard)
        await expect(page).toHaveURL(/registrati/, { timeout: 8_000 });
        // E compare un messaggio di errore nel DOM
        await expect(page.locator('form [class*="text-red"], form [class*="error"], [role="alert"]').first())
            .toBeVisible({ timeout: 8_000 });
    });

    test('email già in uso produce un errore', async ({ page }) => {
        await page.locator('input[name="name"]').fill('Utente Duplicato');
        await page.locator('input[name="email"]').fill(e2eCredentials.email);
        await page.locator('input[name="password"]').fill('password');
        await page.locator('input[name="password_confirmation"]').fill('password');
        await page.locator('[type="submit"]').click();

        await expect(page).toHaveURL(/registrati/, { timeout: 8_000 });
        await expect(page.locator('form [class*="text-red"], form [class*="error"], [role="alert"]').first())
            .toBeVisible({ timeout: 8_000 });
    });

    test('il link "torna al login" porta a /accedi', async ({ page }) => {
        await page.locator('a[href*="accedi"], a[href*="login"]').click();
        await expect(page).toHaveURL('/accedi');
    });
});
