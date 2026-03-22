import { test, expect } from '@playwright/test';

/**
 * Test E2E — Smoke test autenticato
 *
 * Verifica rapida che l'utente autenticato riesca ad accedere alle sezioni principali.
 * Eseguito con la sessione salvata da auth.setup.ts.
 */
test.describe('Smoke test — navigazione autenticata', () => {
    test('la dashboard è raggiungibile dopo l\'autenticazione', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL('/dashboard');
        await expect(page).toHaveTitle(/Dashboard/i);
    });

    test('la pagina dei conti è raggiungibile', async ({ page }) => {
        await page.goto('/accounts');
        await expect(page).toHaveURL('/accounts');
    });

    test('la pagina delle transazioni è raggiungibile', async ({ page }) => {
        await page.goto('/transactions');
        await expect(page).toHaveURL('/transactions');
    });

    test('la pagina delle categorie è raggiungibile', async ({ page }) => {
        await page.goto('/categories');
        await expect(page).toHaveURL('/categories');
    });

    test('la pagina del profilo è raggiungibile', async ({ page }) => {
        await page.goto('/profile');
        await expect(page).toHaveURL('/profile');
    });
});

